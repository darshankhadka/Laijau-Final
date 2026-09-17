<?php

namespace App\Services;

use App\Models\Accounting\JournalEntry;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommerceService
{
    protected SettingsService $settingsService;
    protected InventoryService $inventoryService;
    protected ?AccountingService $accountingService;

    public function __construct(
        SettingsService $settingsService,
        InventoryService $inventoryService,
        ?AccountingService $accountingService = null
    ) {
        $this->settingsService = $settingsService;
        $this->inventoryService = $inventoryService;
        $this->accountingService = $accountingService ?? app(AccountingService::class);
    }

    // ==========================================
    // 1. AVAILABILITY & STOCK VISIBILITY
    // ==========================================

    /**
     * Determine customer-facing stock visibility based on configuration.
     * Modes: 'show_exact', 'show_availability', 'hide'.
     */
    public function getStockVisibility(Product $product, ?ProductVariant $variant = null): array
    {
        $mode = $this->settingsService->getString('commerce', 'stock_visibility', null)
            ?? $this->settingsService->getString('commerce', 'customer_stock_visibility', 'show_availability');

        // Normalize legacy setting values
        if ($mode === 'exact_count') {
            $mode = 'show_exact';
        } elseif ($mode === 'in_stock_badge_only' || $mode === 'low_stock_counter_only') {
            $mode = 'show_availability';
        }

        $stock = $variant ? $variant->available_stock : (int)$product->quantity;
        $isPreorder = $product->isPreorder();

        if ($isPreorder) {
            return [
                'mode' => $mode,
                'visibility' => $mode === 'hide' ? 'hidden' : 'preorder',
                'is_in_stock' => true,
                'is_preorder' => true,
                'available_units' => null,
                'stock_level' => null,
                'badge_label' => 'Pre-order',
                'display_text' => 'Pre-order',
            ];
        }

        if ($mode === 'hide') {
            return [
                'mode' => 'hide',
                'visibility' => 'hidden',
                'is_in_stock' => $stock > 0 || $this->canBackorder(),
                'is_preorder' => false,
                'available_units' => null,
                'stock_level' => null,
                'badge_label' => null,
                'display_text' => null,
            ];
        }

        if ($mode === 'show_exact') {
            $units = max(0, $stock);
            $label = $stock > 0 ? "{$stock} in stock" : ($this->canBackorder() ? 'Available on Backorder' : 'Out of Stock');
            return [
                'mode' => 'show_exact',
                'visibility' => 'exact',
                'is_in_stock' => $stock > 0 || $this->canBackorder(),
                'is_preorder' => false,
                'available_units' => $units,
                'stock_level' => $units,
                'badge_label' => $label,
                'display_text' => $label,
            ];
        }

        // Default: 'show_availability'
        $label = $stock > 0 ? 'In Stock' : ($this->canBackorder() ? 'Available on Backorder' : 'Out of Stock');
        return [
            'mode' => 'show_availability',
            'visibility' => 'availability',
            'is_in_stock' => $stock > 0 || $this->canBackorder(),
            'is_preorder' => false,
            'available_units' => null,
            'stock_level' => null,
            'badge_label' => $label,
            'display_text' => $label,
        ];
    }

    /**
     * Check if backorders are currently permitted by configuration.
     */
    public function canBackorder(): bool
    {
        return $this->settingsService->getBoolean('commerce', 'allow_backorders', false);
    }

    /**
     * Validate whether a product / variant can be purchased in requested quantity.
     */
    public function assertPurchasable(Product $product, ?ProductVariant $variant = null, int $quantity = 1): array
    {
        if (!$product->is_published || !$product->is_active || $product->isPermanentlyUnavailable()) {
            return [
                'purchasable' => false,
                'message' => "{$product->name} is not available for purchase.",
            ];
        }

        if ($variant && (!$variant->is_active || (int)$variant->product_id !== (int)$product->id)) {
            return [
                'purchasable' => false,
                'message' => "The selected product variant for {$product->name} is invalid or no longer available.",
            ];
        }

        if ($product->isPreorder()) {
            $limit = (int)($product->preorder_limit ?? 0);
            $current = (int)($product->preorder_count ?? 0);
            if ($limit > 0 && ($current + $quantity) > $limit) {
                return [
                    'purchasable' => false,
                    'message' => "Pre-order limit of {$limit} pieces reached for {$product->name}.",
                ];
            }

            return [
                'purchasable' => true,
                'is_preorder' => true,
                'is_backorder' => false,
            ];
        }

        $availableStock = $variant ? $variant->available_stock : (int)$product->quantity;
        $allowBackorders = $this->canBackorder();

        if ($availableStock <= 0) {
            if (!$allowBackorders) {
                $itemName = $variant ? "{$product->name} ({$variant->size}/{$variant->color})" : $product->name;
                return [
                    'purchasable' => false,
                    'message' => "Insufficient stock: {$itemName} is currently out of stock.",
                ];
            }

            return [
                'purchasable' => true,
                'is_preorder' => false,
                'is_backorder' => true,
            ];
        }

        if ($product->track_quantity && $quantity > $availableStock) {
            if (!$allowBackorders) {
                $itemName = $variant ? "{$product->name} ({$variant->size}/{$variant->color})" : $product->name;
                return [
                    'purchasable' => false,
                    'message' => "Insufficient stock for {$itemName}. Only {$availableStock} items available.",
                ];
            }

            return [
                'purchasable' => true,
                'is_preorder' => false,
                'is_backorder' => true,
            ];
        }

        return [
            'purchasable' => true,
            'is_preorder' => false,
            'is_backorder' => false,
        ];
    }

    // ==========================================
    // 2. CHECKOUT & RESERVATIONS
    // ==========================================

    /**
     * Validate checkout policies (guest checkout, minimum order value, backorders).
     */
    public function validateCheckoutPolicies(array $customer, float $subtotal, string $currency, ?User $user = null): void
    {
        // 1. Guest checkout check
        $allowGuest = $this->settingsService->has('commerce', 'allow_guest_checkout')
            ? $this->settingsService->getBoolean('commerce', 'allow_guest_checkout', true)
            : $this->settingsService->getBoolean('commerce', 'guest_checkout_allowed', true);

        if (!$user && !$allowGuest) {
            throw new \RuntimeException("Guest checkout is disabled. Please create an account or sign in to complete your order.");
        }

        // 2. Minimum order value check (Nepal NPR or fallback npr)
        $minNpr = $this->settingsService->getDecimal('commerce', 'minimum_order_value_npr', 0.00)
            ?: $this->settingsService->getDecimal('commerce', 'minimum_order_value_npr', 0.00);

        if ($minNpr > 0 && $subtotal < $minNpr) {
            $formattedAmount = number_format($minNpr, 2);
            throw new \RuntimeException("Minimum order subtotal of Rs. {$formattedAmount} is required to checkout.");
        }
    }

    /**
     * Reserve inventory for checkout session honoring configured TTL.
     */
    public function reserveCheckoutStock(string $cartToken, array $items, ?User $user = null): array
    {
        $ttlMinutes = $this->settingsService->getInteger('commerce', 'reservation_ttl_minutes', null)
            ?? $this->settingsService->getInteger('inventory', 'reservation_ttl_minutes', 30);

        $reservations = [];
        $defaultWh = $this->inventoryService->getDefaultWarehouse();

        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? null);
            if (!$product || $product->isPreorder()) {
                continue;
            }

            try {
                $res = $this->inventoryService->createReservation([
                    'warehouse_id' => $defaultWh?->id,
                    'product_id' => $product->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'cart_token' => $cartToken,
                    'reference_type' => 'checkout_cart',
                    'ttl_minutes' => $ttlMinutes,
                ], $user);

                $reservations[] = $res;
            } catch (\Throwable $e) {
                Log::warning("Could not reserve stock during checkout for {$product->sku}: " . $e->getMessage());
            }
        }

        return $reservations;
    }

    // ==========================================
    // 3. PROMOTIONS & COUPON DISCOUNTS
    // ==========================================

    /**
     * Validate and apply coupon code respecting promotion enablement and maximum discount percentage.
     */
    public function applyCouponDiscount(string $code, float $subtotal, string $currency = 'NPR'): array
    {
        $enabled = $this->settingsService->getBoolean('commerce', 'enable_promotions', true);
        if (!$enabled) {
            throw new \RuntimeException("Promotions and coupon codes are currently disabled.");
        }

        $minCartForPromo = $this->settingsService->getDecimal('commerce', 'min_cart_amount_for_promotions', 0.00);
        if ($minCartForPromo > 0 && $subtotal < $minCartForPromo) {
            throw new \RuntimeException("Cart subtotal must be at least Rs. " . number_format($minCartForPromo, 2) . " to use promotional discounts.");
        }

        $coupon = Coupon::where('code', trim($code))->first();
        if (!$coupon) {
            throw new \RuntimeException("Coupon code '{$code}' not found.");
        }

        if (isset($coupon->is_active) && !$coupon->is_active) {
            throw new \RuntimeException("Coupon code '{$code}' is inactive.");
        }

        if ($coupon->expires_at && now()->isAfter($coupon->expires_at)) {
            throw new \RuntimeException("Coupon code '{$code}' has expired.");
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            throw new \RuntimeException("Coupon usage limit reached.");
        }

        if (!empty($coupon->min_spend) && $subtotal < (float)$coupon->min_spend) {
            throw new \RuntimeException("Minimum spend of {$currency} " . number_format((float)$coupon->min_spend, 2) . " required for this coupon.");
        }

        // Calculate nominal coupon discount
        if ($coupon->type === 'percentage') {
            $nominalDiscount = round(($subtotal * ((float)$coupon->value / 100)), 2);
        } else {
            $nominalDiscount = min($subtotal, (float)$coupon->value);
        }

        // Enforce maximum discount percentage ceiling
        $maxDiscountPct = $this->settingsService->getDecimal('commerce', 'max_discount_percentage', 30.00);
        $maxDiscountAllowed = round($subtotal * ($maxDiscountPct / 100), 2);

        $discount = min($nominalDiscount, $maxDiscountAllowed);
        $isCapped = $nominalDiscount > $maxDiscountAllowed;

        return [
            'coupon' => $coupon,
            'code' => $coupon->code,
            'nominal_discount' => $nominalDiscount,
            'discount_amount' => $discount,
            'is_capped' => $isCapped,
            'max_discount_allowed' => $maxDiscountAllowed,
            'max_discount_percentage' => $maxDiscountPct,
        ];
    }

    // ==========================================
    // 4. ORDERING & STATE MACHINE
    // ==========================================

    /**
     * Generate sequential authoritative order number.
     */
    public function generateOrderNumber(): string
    {
        return Order::generateNextOrderNumber();
    }

    /**
     * Ship order, assigning courier tracking and advancing status.
     */
    public function shipOrder(Order $order, string $carrier = '', string $trackingNumber = '', ?User $user = null): Order
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])) {
            throw new \RuntimeException("Cannot ship order #{$order->order_number} in '{$order->status}' status.");
        }

        $requireTracking = $this->settingsService->getBoolean('shipping', 'require_tracking_number_on_dispatch', true);
        $finalTracking = trim($trackingNumber ?: ($order->tracking_number ?? ''));

        if ($requireTracking && empty($finalTracking)) {
            throw new \RuntimeException("A valid parcel tracking number is required before dispatching order #{$order->order_number}.");
        }

        $defaultCarrier = $this->settingsService->getString('shipping', 'default_shipping_carrier', 'Nepal Can Move (NCM)');
        $finalCarrier = trim($carrier ?: ($order->carrier ?: $defaultCarrier));

        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'carrier' => $finalCarrier,
            'tracking_number' => $finalTracking ?: null,
            'tracking_url' => Order::resolveTrackingUrl($finalCarrier, $finalTracking),
        ]);

        return $order;
    }

    /**
     * Mark order as delivered, recording authoritative delivered_at timestamp.
     */
    public function markDelivered(Order $order, ?User $user = null): Order
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])) {
            throw new \RuntimeException("Cannot mark order #{$order->order_number} as delivered in '{$order->status}' status.");
        }

        $order->update([
            'status' => Order::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        return $order;
    }

    // ==========================================
    // 5. CANCELLATION ENGINE
    // ==========================================

    /**
     * Check whether an order is eligible for cancellation.
     */
    public function canCancelOrder(Order $order, bool $isCustomerSelfService = true): array
    {
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED, Order::STATUS_DELIVERED])) {
            return [
                'can_cancel' => false,
                'reason' => "Order #{$order->order_number} is already {$order->status} and cannot be cancelled.",
            ];
        }

        if ($isCustomerSelfService) {
            $windowMinutes = $this->settingsService->has('commerce', 'order_cancellation_window_minutes')
                ? $this->settingsService->getInteger('commerce', 'order_cancellation_window_minutes', 60)
                : $this->settingsService->getInteger('commerce', 'cancellation_window_minutes', 60);

            $createdAt = $order->created_at ?? now();
            $elapsedMinutes = $createdAt->diffInMinutes(now());

            if ($elapsedMinutes > $windowMinutes) {
                return [
                    'can_cancel' => false,
                    'reason' => "The self-service cancellation window of {$windowMinutes} minutes has expired. Please contact customer care.",
                ];
            }
        }

        return [
            'can_cancel' => true,
            'reason' => null,
        ];
    }

    /**
     * Cancel order, release inventory/stock, reverse accounting GL vouchers, and update status.
     */
    public function cancelOrder(Order $order, string $reason = '', ?User $user = null, bool $isCustomerSelfService = false): Order
    {
        $check = $this->canCancelOrder($order, $isCustomerSelfService);
        if (!$check['can_cancel']) {
            throw new \RuntimeException($check['reason']);
        }

        return DB::transaction(function () use ($order, $reason, $user, $isCustomerSelfService) {
            $order->loadMissing('items');

            // 1. Release or restore inventory
            if (in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_PACKING, Order::STATUS_READY_TO_SHIP, Order::STATUS_SHIPPED])) {
                $restoreLines = [];
                foreach ($order->items as $item) {
                    $restoreLines[] = [
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->quantity,
                    ];
                }

                if (!empty($restoreLines)) {
                    try {
                        $this->inventoryService->restoreOrderReturn($order, $restoreLines, "Order Cancellation: {$reason}", $user);
                    } catch (\Throwable $e) {
                        Log::error("Inventory restoration failed for cancelled order #{$order->order_number}: " . $e->getMessage());
                    }
                }
            }

            // 2. Reverse accounting journal entries if posted
            if ($this->accountingService) {
                try {
                    $voucher = JournalEntry::where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('status', 'posted')
                        ->first();

                    if ($voucher) {
                        $this->accountingService->reverseJournalEntry(
                            $voucher,
                            "Cancellation of webshop order #{$order->order_number}: {$reason}",
                            $user
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting reversal failed for cancelled order #{$order->order_number}: " . $e->getMessage());
                }
            }

            // 3. Mutate order status
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason ?: ($isCustomerSelfService ? 'Customer self-service cancellation' : 'Admin cancellation'),
            ]);

            return $order;
        });
    }

    // ==========================================
    // 6. RETURNS & REFUND ENGINE
    // ==========================================

    /**
     * Check whether an order is eligible for return.
     */
    public function canReturnOrder(Order $order): array
    {
        if (!in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_SHIPPED])) {
            return [
                'can_return' => false,
                'reason' => "Order #{$order->order_number} must be delivered before initiating a return (currently '{$order->status}').",
            ];
        }

        $returnWindowDays = $this->settingsService->getInteger('commerce', 'return_window_days', 14);
        $deliveredAt = $order->delivered_at ?? $order->updated_at ?? now();
        $elapsedDays = $deliveredAt->diffInDays(now());

        if ($elapsedDays > $returnWindowDays) {
            return [
                'can_return' => false,
                'reason' => "The return window of {$returnWindowDays} days has elapsed since delivery ({$elapsedDays} days elapsed).",
            ];
        }

        return [
            'can_return' => true,
            'reason' => null,
        ];
    }

    /**
     * Process order return, apply restocking fees, optionally restore stock, and update ledger.
     */
    public function processOrderReturn(Order $order, array $returnItems, string $reason = '', ?User $user = null): Order
    {
        $check = $this->canReturnOrder($order);
        if (!$check['can_return']) {
            throw new \RuntimeException($check['reason']);
        }

        $restockingFeePct = $this->settingsService->getDecimal('commerce', 'restocking_fee_percentage', 0.00);
        $autoRestock = $this->settingsService->getBoolean('commerce', 'auto_restock_on_refund', true);

        return DB::transaction(function () use ($order, $returnItems, $reason, $user, $restockingFeePct, $autoRestock) {
            $order->load('items');

            $grossRefund = 0.00;
            $itemsToRestock = [];

            foreach ($returnItems as $line) {
                $orderItem = $order->items->firstWhere('id', $line['order_item_id'] ?? null);
                if (!$orderItem) {
                    continue;
                }

                $qty = min((int)($line['quantity'] ?? 1), (int)$orderItem->quantity);
                $unitPrice = (float)$orderItem->unit_price;
                $lineTotal = round($qty * $unitPrice, 2);
                $grossRefund += $lineTotal;

                $itemsToRestock[] = [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'variant_id' => $orderItem->variant_id,
                    'quantity' => $qty,
                    'disposition' => $line['disposition'] ?? 'sellable',
                ];
            }

            $restockingFee = round($grossRefund * ($restockingFeePct / 100), 2);

            $returnShippingCoveredBy = $this->settingsService->getString('shipping', 'return_shipping_covered_by', 'customer');
            $currency = strtoupper($order->currency ?? 'NPR');
            $returnShippingFee = 0.00;

            if ($returnShippingCoveredBy === 'customer') {
                $returnShippingFee = $this->settingsService->getDecimal('shipping', 'return_shipping_fee_npr', 0.00);
                if ($returnShippingFee <= 0) {
                    $returnShippingFee = $this->settingsService->getDecimal('shipping', 'return_shipping_fee_npr', 0.00);
                }
            }

            $totalDeductions = round($restockingFee + $returnShippingFee, 2);
            $netRefund = max(0.00, round($grossRefund - $totalDeductions, 2));

            // 1. Inventory restock via InventoryService if configured
            if ($autoRestock && !empty($itemsToRestock)) {
                try {
                    $disposition = $returnItems[0]['disposition'] ?? 'sellable';
                    $this->inventoryService->processCustomerReturn(
                        $order,
                        $itemsToRestock,
                        $disposition,
                        "Customer Return: {$reason}",
                        $user
                    );
                } catch (\Throwable $e) {
                    Log::error("Inventory return processing error for order #{$order->order_number}: " . $e->getMessage());
                }
            }

            // 2. Adjust or post accounting return voucher
            if ($this->accountingService && $netRefund > 0) {
                try {
                    $voucher = JournalEntry::where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('status', 'posted')
                        ->first();

                    if ($voucher) {
                        $this->accountingService->reverseJournalEntry(
                            $voucher,
                            "Return & refund for webshop order #{$order->order_number} ({$reason})",
                            $user
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning("Accounting return voucher generation skipped for order #{$order->order_number}: " . $e->getMessage());
                }
            }

            // 3. Update order refund state
            $order->update([
                'status' => Order::STATUS_REFUNDED,
                'payment_status' => 'refunded',
                'refunded_at' => now(),
                'refunded_amount' => $netRefund,
                'restocking_fee' => $restockingFee,
                'internal_notes' => trim(($order->internal_notes ?? '') . "\nReturn processed: {$reason} (Refund: {$netRefund} {$order->currency}, Restock Fee: {$restockingFee}, Return Post: {$returnShippingFee})"),
            ]);

            return $order;
        });
    }

    /**
     * Handle failed carrier delivery attempt according to configured logistics policy.
     */
    public function handleFailedDelivery(Order $order, string $reason = '', ?User $user = null): Order
    {
        $action = $this->settingsService->getString('shipping', 'failed_delivery_action', 'return_to_warehouse');

        return DB::transaction(function () use ($order, $reason, $user, $action) {
            $order->loadMissing('items');

            if ($action === 'return_to_warehouse') {
                $restoreLines = [];
                foreach ($order->items as $item) {
                    $restoreLines[] = [
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->quantity,
                    ];
                }

                if (!empty($restoreLines)) {
                    try {
                        $this->inventoryService->restoreOrderReturn($order, $restoreLines, "Failed Delivery Return: {$reason}", $user);
                    } catch (\Throwable $e) {
                        Log::error("Failed delivery inventory restock error for #{$order->order_number}: " . $e->getMessage());
                    }
                }

                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => "Failed Delivery (Returned to Warehouse): {$reason}",
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nFailed delivery processed: {$reason}. Consignment returned to warehouse."),
                ]);
            } elseif ($action === 'quarantine') {
                $order->update([
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nFailed delivery: {$reason}. Marked for quarantine inspection."),
                ]);
            } else {
                // reschedule
                $order->update([
                    'internal_notes' => trim(($order->internal_notes ?? '') . "\nFailed delivery: {$reason}. On hold for carrier rescheduling."),
                ]);
            }

            return $order;
        });
    }
}
