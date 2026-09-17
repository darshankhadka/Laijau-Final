<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Services\NepalLocationService;
use App\Services\TaxCalculatorService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected TaxCalculatorService $taxCalculator;

    public function __construct(TaxCalculatorService $taxCalculator)
    {
        $this->taxCalculator = $taxCalculator;
    }

    /**
     * Validate cart items, coupon, calculate subtotal, shipping, and total for Laijau Nepal.
     */
    public function validateCart(Request $request)
    {
        if (!$request->has('shipping_country') && $request->has('country')) {
            $request->merge(['shipping_country' => $request->input('country')]);
        }

        $country = $request->input('shipping_country', 'NP');
        $request->merge([
            'shipping_country' => TaxCalculatorService::normalizeCountryCode($country)
        ]);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'nullable|integer',
            'shipping_country' => 'nullable|string|size:2',
            'district' => 'nullable|string|max:100',
            'shipping_method_code' => 'nullable|string',
            'currency' => 'nullable|string|in:NPR,npr,npr,USD',
            'coupon_code' => 'nullable|string',
        ]);

        $items = $request->input('items');
        $currency = 'NPR';
        $shippingCountry = TaxCalculatorService::normalizeCountryCode($request->input('shipping_country', 'NP'));
        $district = $request->input('district');
        $shippingMethodCode = $request->input('shipping_method_code');

        $subtotal = 0.0;
        $validatedItems = [];
        $stockErrors = [];

        // Batch fetch products and variants
        $productIds = array_unique(array_column($items, 'product_id'));
        $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $variantIds = array_unique(array_filter(array_column($items, 'variant_id')));
        $variantsById = !empty($variantIds) ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id') : collect();

        foreach ($items as $item) {
            $product = $productsById->get($item['product_id']);
            if (!$product) {
                $stockErrors[] = "Product no longer available.";
                continue;
            }

            if (!$product->is_published || !$product->is_active || $product->isPermanentlyUnavailable()) {
                $stockErrors[] = "{$product->name} is currently unavailable and cannot be purchased.";
                continue;
            }

            if (!empty($item['variant_id'])) {
                $variant = $variantsById->get($item['variant_id']);
                if (!$variant || (int)$variant->product_id !== (int)$product->id || !$variant->is_active) {
                    $stockErrors[] = "The selected product variant for {$product->name} is invalid or no longer available.";
                    continue;
                }
            } else {
                $variant = null;
            }
            $isPreorder = $product->isPreorder();
            $stockAvailable = $variant ? $variant->stock_quantity : (int)$product->quantity;
            if (!$isPreorder && ($product->track_quantity ?? true) && ($stockAvailable < (int)$item['quantity'] || $product->availability_status === 'out_of_stock')) {
                $stockErrors[] = "Insufficient stock for {$product->name}. Only {$stockAvailable} available.";
                continue;
            }

            // Determine unit price (NPR authoritative)
            if ($variant) {
                $unitPrice = (float)($variant->price ?: ($variant->price_npr ?: ($product->price ?: ($product->price_npr ?: 0))));
            } else {
                $unitPrice = (float)($product->price ?: ($product->price_npr ?: 0));
            }

            $lineTotal = $unitPrice * $item['quantity'];
            $subtotal += $lineTotal;

            $validatedItems[] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'sku' => $variant ? $variant->sku : $product->sku,
                'quantity' => $item['quantity'],
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($lineTotal, 2),
                'is_preorder' => $isPreorder,
                'preorder_dispatch_note' => $isPreorder ? ($product->preorder_expected_dispatch ?: '15–20 business days') : null,
                'selected_color' => $item['selected_color'] ?? $variant?->color,
                'selected_size' => $item['selected_size'] ?? $variant?->size,
            ];
        }

        // Coupon calculation
        $couponDiscount = 0.0;
        $couponMessage = null;
        $couponValid = false;

        if ($request->filled('coupon_code')) {
            $couponCode = trim($request->input('coupon_code'));
            $coupon = Coupon::where('code', $couponCode)
                ->where('is_active', true)
                ->first();

            if ($coupon && $coupon->isValid()) {
                $couponValid = true;
                if ($coupon->type === 'percentage') {
                    $couponDiscount = round(($subtotal * $coupon->value) / 100, 2);
                } else {
                    $couponDiscount = min($subtotal, (float)$coupon->value);
                }
                $couponMessage = "Coupon '{$coupon->code}' applied successfully!";
            } else {
                $couponMessage = "Invalid or expired coupon code.";
            }
        }

        $discountedSubtotal = max(0, $subtotal - $couponDiscount);

        // Calculate Nepal Shipping Fee based on District
        $shippingFee = NepalLocationService::calculateShippingFee($district, $discountedSubtotal);
        $availableShippingMethods = ShippingMethod::getAvailableMethodsForCountry('NP', $discountedSubtotal, $currency, $district);
        $eligiblePaymentMethods = NepalLocationService::getEligiblePaymentMethods($district);
        $isInsideValley = NepalLocationService::isKathmanduValley($district);

        $total = $discountedSubtotal + $shippingFee;

        if (!empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => implode(' ', $stockErrors),
                'stock_errors' => $stockErrors,
                'errors' => $stockErrors,
            ], 422);
        }

        return response()->json([
            'success' => count($stockErrors) === 0,
            'currency' => $currency,
            'subtotal' => round($subtotal, 2),
            'discount' => round($couponDiscount, 2),
            'discounted_subtotal' => round($discountedSubtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'total' => round($total, 2),
            'items' => $validatedItems,
            'stock_errors' => $stockErrors,
            'errors' => $stockErrors,
            'coupon' => [
                'code' => $request->input('coupon_code'),
                'valid' => $couponValid,
                'discount' => $couponDiscount,
                'message' => $couponMessage,
            ],
            'shipping_methods' => $availableShippingMethods,
            'eligible_payment_methods' => $eligiblePaymentMethods,
            'is_inside_valley' => $isInsideValley,
            'cod_available' => $isInsideValley,
        ]);
    }
}
