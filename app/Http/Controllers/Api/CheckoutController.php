<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Models\PaymentAuditLog;
use App\Models\ShippingMethod;
use App\Models\Setting;
use App\Services\CommerceService;
use App\Services\Inventory\InventoryService;
use App\Services\NepalLocationService;
use App\Services\Security\ImageUploadSecurityService;
use App\Services\TaxCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    protected TaxCalculatorService $taxCalculator;
    protected InventoryService $inventoryService;
    protected CommerceService $commerceService;

    public function __construct(
        TaxCalculatorService $taxCalculator,
        InventoryService $inventoryService,
        CommerceService $commerceService
    ) {
        $this->taxCalculator = $taxCalculator;
        $this->inventoryService = $inventoryService;
        $this->commerceService = $commerceService;
    }

    /**
     * Process checkout, enforce Nepal COD rules, reserve stock, and create order.
     */
    public function process(Request $request)
    {
        $rawCustomer = $request->input('customer');
        if (!is_array($rawCustomer)) {
            return response()->json(['success' => false, 'message' => 'Customer information is required.'], 422);
        }

        $request->validate([
            'customer' => 'required|array',
            'customer.first_name' => 'required|string|max:100',
            'customer.last_name' => 'required|string|max:100',
            'customer.phone' => 'required|string|max:50',
            'customer.alt_phone' => 'nullable|string|max:50',
            'customer.email' => 'nullable|email|max:255',
            'customer.province' => 'required|string|max:100',
            'customer.district' => 'required|string|max:100',
            'customer.municipality' => 'required|string|max:150',
            'customer.ward' => 'required|string|max:20',
            'customer.tole' => 'required|string|max:150',
            'customer.landmark' => 'nullable|string|max:255',
            'customer.notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|string|in:cod,connectips,esewa,khalti,bank_transfer',
            'payment_reference' => 'nullable|string|max:150',
            'payment_receipt' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'nullable|integer',
            'currency' => 'nullable|string|in:NPR,npr',
            'shipping_method_code' => 'nullable|string',
            'coupon_code' => 'nullable|string',
        ]);

        $customer = $request->input('customer');
        $district = trim($customer['district']);
        $paymentMethod = $request->input('payment_method');
        $currency = 'NPR';
        $items = $request->input('items');

        // RULE: Cash on Delivery is strictly available ONLY inside Kathmandu Valley
        $isInsideValley = NepalLocationService::isCodAvailable($district);
        if ($paymentMethod === 'cod' && !$isInsideValley) {
            return response()->json([
                'success' => false,
                'message' => "Cash on Delivery (COD) is available ONLY inside Kathmandu Valley (Kathmandu, Lalitpur, Bhaktapur). For delivery to {$district}, please choose connectIPS or eSewa QR payment.",
            ], 422);
        }

        // Section 18: Checkout Idempotency & Duplicate Order Protection
        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?: $request->input('idempotency_key')
            ?: md5(
                trim($customer['phone'] ?? '') . '|' .
                    trim($customer['first_name'] ?? '') . '|' .
                    trim($district) . '|' .
                    trim($paymentMethod) . '|' .
                    json_encode(collect($items)->map(fn($it) => ['p' => $it['product_id'] ?? null, 'v' => $it['variant_id'] ?? null, 'q' => $it['quantity'] ?? null])->values()->all())
            );

        $cachedOrderNumber = Cache::get("order_idempotency_{$idempotencyKey}");
        if ($cachedOrderNumber) {
            $existingOrder = Order::where('order_number', $cachedOrderNumber)->first();
            if ($existingOrder) {
                return response()->json([
                    'success' => true,
                    'order_id' => $existingOrder->id,
                    'order_number' => $existingOrder->order_number,
                    'guest_access_token' => $existingOrder->guest_access_token,
                    'payment_method' => $existingOrder->payment_method,
                    'payment_status' => $existingOrder->payment_status,
                    'order_status' => $existingOrder->status,
                    'total_amount' => (float)$existingOrder->total_amount,
                    'currency' => $currency,
                    'is_inside_valley' => $isInsideValley,
                    'redirect_url' => "/checkout/success?order_number={$existingOrder->order_number}&token={$existingOrder->guest_access_token}",
                    'message' => "Order #{$existingOrder->order_number} has already been received!",
                    'idempotent_replay' => true,
                ]);
            }
        }

        $lockKey = "checkout_lock_{$idempotencyKey}";
        $lock = Cache::lock($lockKey, 15);
        if (!$lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'An order submission is already in progress. Please wait a moment before trying again.',
            ], 429);
        }

        // Section 20: Secure Payment Receipt Upload Validation
        $paymentReceiptPath = null;
        if ($request->hasFile('payment_receipt')) {
            $file = $request->file('payment_receipt');
            try {
                ImageUploadSecurityService::validateImageFile($file);
                $filename = ImageUploadSecurityService::generateSafeFilename($file, 'proof');
                $paymentReceiptPath = $file->storeAs('payment_proofs', $filename, 'local');
            } catch (\Throwable $e) {
                $lock->release();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment receipt image: ' . $e->getMessage(),
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $subtotal = 0.0;
            $orderItemsData = [];

            // Deterministic row locking in numerical order to prevent deadlocks under concurrency
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->sort()->values()->all();
            $lockedProducts = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $variantIds = collect($items)->pluck('variant_id')->filter()->unique()->sort()->values()->all();
            $lockedVariants = !empty($variantIds)
                ? ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            foreach ($items as $item) {
                $product = $lockedProducts->get((int)$item['product_id']);
                if (!$product || !$product->is_published || !$product->is_active || $product->isPermanentlyUnavailable()) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => ($product?->name ?? 'Selected product') . " is not available for purchase.",
                    ], 422);
                }

                if (!empty($item['variant_id'])) {
                    $variant = $lockedVariants->get((int)$item['variant_id']);
                    if (!$variant || (int)$variant->product_id !== (int)$product->id || !$variant->is_active) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "The selected product variant for {$product->name} is invalid or no longer available.",
                        ], 422);
                    }
                } else {
                    $variant = null;
                }

                // Check stock availability
                $stockAvailable = $variant ? $variant->stock_quantity : (int)$product->quantity;
                $isPreorder = $product->isPreorder();

                if (!$isPreorder && $product->track_quantity && $item['quantity'] > $stockAvailable) {
                    DB::rollBack();
                    $name = $variant ? "{$product->name} ({$variant->size}/{$variant->color})" : $product->name;
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$name}. Only {$stockAvailable} available.",
                    ], 422);
                }

                // Pricing (NPR authoritative)
                if ($variant) {
                    $unitPrice = (float)($variant->price ?: ($variant->price_npr ?: ($product->price ?: ($product->price_npr ?: 0))));
                    $sku = $variant->sku;
                } else {
                    $unitPrice = (float)($product->price ?: ($product->price_npr ?: 0));
                    $sku = $product->sku;
                }

                // Permanent Zero-Price Safeguard (Strict Global Requirement)
                if ($unitPrice <= 0.0) {
                    DB::rollBack();
                    Log::error("Zero-price checkout attempt blocked for product #{$product->id} ({$product->name})", [
                        'item' => $item,
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "Item '{$product->name}' does not have an established selling price and cannot be purchased. Please contact customer support.",
                    ], 422);
                }

                $lineTotal = $unitPrice * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'selected_color' => $item['selected_color'] ?? $variant?->color ?? 'Default',
                    'selected_size' => $item['selected_size'] ?? $variant?->size ?? 'Standard',
                    'custom_measurements' => null,
                    'is_preorder' => $isPreorder,
                    'preorder_dispatch_note' => $isPreorder ? ($product->preorder_expected_dispatch ?: '15–20 business days') : null,
                ];
            }

            // Validate checkout policies (guest checkout, minimum order value)
            try {
                $this->commerceService->validateCheckoutPolicies($customer, $subtotal, $currency, auth('web')->user());
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            // Coupon discount calculation
            $couponDiscount = 0.0;
            $appliedCoupon = null;

            if ($request->filled('coupon_code')) {
                $couponCode = trim($request->input('coupon_code'));
                $coupon = Coupon::where('code', $couponCode)->where('is_active', true)->first();
                if ($coupon && $coupon->isValid()) {
                    if ($coupon->type === 'percentage') {
                        $couponDiscount = round(($subtotal * $coupon->value) / 100, 2);
                    } else {
                        $couponDiscount = min($subtotal, (float)$coupon->value);
                    }
                    $coupon->increment('used_count');
                }
            }

            $discountedSubtotal = max(0, $subtotal - $couponDiscount);

            // Shipping rate based on district
            $shippingFee = NepalLocationService::calculateShippingFee($district, $discountedSubtotal);
            $totalAmount = $discountedSubtotal + $shippingFee;

            // Determine Payment and Order Status
            $connectIpsTxnId = null;
            if ($paymentMethod === 'cod') {
                $paymentStatus = Order::PAYMENT_STATUS_UNPAID;
                $orderStatus = Order::STATUS_PENDING;
            } elseif ($paymentMethod === 'connectips') {
                $paymentStatus = Order::PAYMENT_STATUS_UNPAID;
                $orderStatus = Order::STATUS_PENDING;
                $connectIpsTxnId = 'ORD_' . time() . '_' . Str::upper(Str::random(6));
            } else {
                // esewa and manual bank transfer
                $paymentStatus = Order::PAYMENT_STATUS_VERIFICATION_PENDING;
                $orderStatus = Order::STATUS_PENDING;
            }

            // Construct full address string
            $addressParts = array_filter([
                $customer['tole'] ?? '',
                isset($customer['ward']) ? 'Ward ' . $customer['ward'] : null,
                !empty($customer['landmark']) ? 'Near ' . $customer['landmark'] : null,
                $customer['municipality'] ?? '',
                $customer['district'] ?? '',
                $customer['province'] ?? '',
                'Nepal'
            ]);
            $fullShippingAddress = implode(', ', $addressParts);

            // Generate identifiers
            $orderNumber = Order::generateNextOrderNumber();
            $guestToken = Order::generateGuestToken();
            $authUser = auth('web')->user() ?? $request->user('sanctum');

            $order = Order::create([
                'order_number' => $orderNumber,
                'channel' => Order::CHANNEL_ONLINE,
                'guest_access_token' => $guestToken,
                'user_id' => $authUser?->id,
                'first_name' => trim($customer['first_name']),
                'last_name' => trim($customer['last_name']),
                'phone' => trim($customer['phone']),
                'alt_phone' => !empty($customer['alt_phone']) ? trim($customer['alt_phone']) : null,
                'email' => !empty($customer['email']) ? trim($customer['email']) : "guest_{$orderNumber}@laijau.com",
                'province' => $customer['province'],
                'district' => $district,
                'municipality' => $customer['municipality'],
                'ward' => $customer['ward'],
                'tole' => $customer['tole'],
                'landmark' => $customer['landmark'] ?? null,
                'is_inside_valley' => $isInsideValley,
                'shipping_address' => $fullShippingAddress,
                'shipping_city' => $customer['municipality'],
                'shipping_country' => 'NP',
                'shipping_method' => $isInsideValley ? 'Kathmandu Valley Delivery' : 'Outside Valley Courier (NCM/Pathao)',
                'shipping_fee' => $shippingFee,
                'courier_name' => $isInsideValley ? 'Pathao Local' : 'NCM',
                'subtotal' => round($subtotal, 2),
                'coupon_discount' => round($couponDiscount, 2),
                'vat_rate' => 0.00,
                'vat_amount' => 0.00,
                'total_amount' => round($totalAmount, 2),
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $request->input('payment_reference'),
                'payment_receipt_image' => $paymentReceiptPath,
                'connectips_txnid' => $connectIpsTxnId,
                'status' => $orderStatus,
                'customer_notes' => $customer['notes'] ?? null,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // Reserve/Deduct Inventory atomically
            $defaultWh = $this->inventoryService->getDefaultWarehouse();
            foreach ($orderItemsData as $itemData) {
                $product = $lockedProducts->get($itemData['product_id']);
                if ($product && $product->track_quantity) {
                    try {
                        $this->inventoryService->createReservation([
                            'warehouse_id' => $defaultWh?->id,
                            'product_id' => $product->id,
                            'variant_id' => $itemData['variant_id'],
                            'quantity' => $itemData['quantity'],
                            'reference_type' => 'online_order',
                            'reference_id' => $order->id,
                            'cart_token' => $order->order_number,
                            'ttl_minutes' => 1440, // 24 hours reservation pending verification/dispatch
                        ], $authUser);
                    } catch (\Throwable $e) {
                        Log::warning("Reservation skipped for {$product->sku}: " . $e->getMessage());
                    }
                }
            }

            DB::commit();

            // Dispatch automatic print jobs to Showroom Print Agent (idempotent)
            try {
                app(\App\Services\PrintAgent\PrintAgentService::class)->queueOnlineOrderReceipt($order);
                app(\App\Services\PrintAgent\PrintAgentService::class)->queueOnlineOrderPackingSlip($order);
            } catch (\Throwable $printEx) {
                Log::warning('PrintAgent queue error for online order: ' . $printEx->getMessage());
            }

            // Record initial payment audit log
            PaymentAuditLog::record(
                $order,
                $paymentStatus,
                null,
                $request->input('payment_reference') ?: $connectIpsTxnId,
                'customer',
                $authUser?->id ? (string)$authUser->id : null,
                null,
                "Order created via checkout with method {$paymentMethod}."
            );

            // Store placed order in session for guest tracking
            if ($request->hasSession()) {
                $placed = (array) $request->session()->get('placed_orders', []);
                $placed[] = $order->order_number;
                $request->session()->put('placed_orders', array_unique($placed));
            }

            // Determine redirect URL
            if ($paymentMethod === 'connectips') {
                $redirectUrl = route('payment.connectips.initiate', [
                    'order' => $order->id,
                    'token' => $guestToken,
                ]);
            } else {
                $redirectUrl = "/checkout/success?order_number={$order->order_number}&token={$guestToken}";
            }

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'guest_access_token' => $guestToken,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'is_inside_valley' => $isInsideValley,
                'redirect_url' => $redirectUrl,
                'message' => "Order #{$order->order_number} has been successfully placed!",
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Checkout processing failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Unable to complete order. " . $e->getMessage(),
            ], 500);
        } finally {
            if (isset($order) && $order->order_number) {
                Cache::put("order_idempotency_{$idempotencyKey}", $order->order_number, 60);
            }
            if (isset($lock)) {
                $lock->release();
            }
        }
    }

    /**
     * Look up order status by order number and phone or guest token.
     */
    public function lookup(Request $request, string $orderNumber)
    {
        $query = Order::where('order_number', $orderNumber)->with(['items']);

        $token = $request->query('token') ?? $request->input('token');
        $phone = $request->query('phone') ?? $request->input('phone');
        $sessionId = $request->query('session_id') ?? $request->input('session_id');

        $order = $query->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (!$this->isAuthorizedForOrder($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Please provide phone number or tracking token to access this order.'], 403);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->format('M d, Y h:i A'),
                'customer_name' => $order->customer_full_name,
                'phone' => $order->phone,
                'shipping_address' => $order->full_address,
                'is_inside_valley' => (bool)$order->is_inside_valley,
                'subtotal' => (float)$order->subtotal,
                'shipping_fee' => (float)$order->shipping_fee,
                'discount' => (float)$order->coupon_discount,
                'total_amount' => (float)$order->total_amount,
                'currency' => $order->currency ?: 'NPR',
                'status' => $order->status,
                'status_label' => Order::getStatuses()[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status)),
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'payment_reference' => $order->payment_reference,
                'courier_name' => $order->courier_name ?: $order->carrier,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url ?: Order::resolveTrackingUrl($order->courier_name ?: $order->carrier, $order->tracking_number),
                'estimated_delivery_date' => $order->estimated_delivery_date?->format('M d, Y'),
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'sku' => $item->sku,
                        'color' => $item->selected_color,
                        'size' => $item->selected_size,
                        'quantity' => $item->quantity,
                        'unit_price' => (float)$item->unit_price,
                        'total_price' => (float)($item->unit_price * $item->quantity),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Backward compatible payment confirmation helper
     */
    public static function confirmOrderPayment(Order $order, string|object $sessionOrIntentId, ?string $eventId = null): Order
    {
        $paymentIntentId = is_object($sessionOrIntentId)
            ? ($sessionOrIntentId->payment_intent ?? $sessionOrIntentId->id ?? 'pi_mock')
            : (string) $sessionOrIntentId;

        if ($order->payment_status === 'paid') {
            return $order;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $paymentIntentId) {
            $order->update([
                'status' => Order::STATUS_PROCESSING,
                'payment_status' => 'paid',
                'payment_id' => $paymentIntentId,
                'payment_verified_at' => now(),
            ]);

            try {
                $wh = app(\App\Services\Inventory\InventoryService::class)->getDefaultWarehouse();
                foreach ($order->items as $item) {
                    if ($item->product && $item->product->track_quantity) {
                        app(\App\Services\Inventory\InventoryService::class)->recordStockMovement([
                            'warehouse_id' => $wh->id,
                            'product_id' => $item->product_id,
                            'variant_id' => $item->variant_id,
                            'quantity' => -1 * (int)$item->quantity,
                            'movement_type' => 'sale_order',
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'notes' => "Order #{$order->order_number} payment confirmed",
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Payment confirmation stock deduction failed: " . $e->getMessage());
                foreach ($order->items as $item) {
                    if ($item->variant_id) {
                        ProductVariant::where('id', $item->variant_id)->decrement('stock_quantity', (int)$item->quantity);
                    }
                    if ($item->product && $item->product->track_quantity) {
                        $item->product->decrement('quantity', (int)$item->quantity);
                    }
                }
            }

            try {
                \App\Jobs\SendOrderConfirmationEmailJob::dispatchSync($order->id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Order confirmation email failed: " . $e->getMessage());
            }
        });

        return $order->fresh();
    }

    public function cancel(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (!$this->isAuthorizedForOrder($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to order.'], 403);
        }
        $user = auth('web')->user() ?? $request->user('sanctum');

        $reason = $request->input('reason', 'Customer requested cancellation');

        try {
            $updatedOrder = $this->commerceService->cancelOrder($order, $reason, $user, true);

            return response()->json([
                'success' => true,
                'message' => 'Order has been cancelled successfully.',
                'order' => [
                    'order_number' => $updatedOrder->order_number,
                    'status' => $updatedOrder->status,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function returnOrder(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (!$this->isAuthorizedForOrder($request, $order)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to order.'], 403);
        }
        $user = auth('web')->user() ?? $request->user('sanctum');

        $items = $request->input('items', []);
        $reason = $request->input('reason', 'Customer requested return');

        try {
            $updatedOrder = $this->commerceService->processOrderReturn($order, $items, $reason, $user);

            return response()->json([
                'success' => true,
                'message' => 'Order return has been processed successfully.',
                'order' => [
                    'order_number' => $updatedOrder->order_number,
                    'status' => $updatedOrder->status,
                    'refunded_amount' => (float)($updatedOrder->refunded_amount ?? 0),
                    'restocking_fee' => (float)($updatedOrder->restocking_fee ?? 0),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if the incoming request is authorized to view or modify the order.
     */
    protected function isAuthorizedForOrder(Request $request, Order $order): bool
    {
        // 1. Staff / Admin check
        if (auth('admin')->check()) {
            return true;
        }

        // 2. Authenticated customer ownership check
        $authUser = auth('web')->user() ?? $request->user('sanctum');
        if ($authUser && $order->user_id && (int)$authUser->id === (int)$order->user_id) {
            return true;
        }

        // 3. Cryptographically timing-safe guest token check
        $token = $request->query('token') ?? $request->input('token');
        if ($token && !empty($order->guest_access_token) && hash_equals((string)$order->guest_access_token, (string)$token)) {
            return true;
        }

        // 4. Payment session token check
        $sessionId = $request->query('session_id') ?? $request->input('session_id');
        if ($sessionId && $order->payment_id === $sessionId) {
            return true;
        }

        // 5. Verification by phone (requires full number match with min 9 digits)
        $phone = $request->query('phone') ?? $request->input('phone');
        if (!empty($phone) && !empty($order->phone)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
            $orderPhone = preg_replace('/[^0-9]/', '', (string)$order->phone);
            if (strlen($cleanPhone) >= 9 && str_ends_with($orderPhone, $cleanPhone)) {
                return true;
            }
        }

        // 6. Session placed order verification
        if ($request->hasSession() && in_array($order->order_number, (array)$request->session()->get('placed_orders', []), true)) {
            return true;
        }

        return false;
    }
}
