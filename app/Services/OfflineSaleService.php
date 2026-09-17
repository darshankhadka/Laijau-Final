<?php

namespace App\Services;

use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\OfflineSaleVoidLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineSaleService
{
    /**
     * Atomically record an offline sale and update inventory.
     */
    public function createSale(array $saleData, array $itemsData, ?User $staffUser = null): OfflineSale
    {
        if (!app(\App\Services\Settings\SettingsService::class)->getBoolean('pos', 'offline_sales_enabled', true)) {
            throw new \RuntimeException("Offline/POS sales are currently disabled in Module Settings.");
        }

        if (empty($itemsData)) {
            throw new \InvalidArgumentException("Cannot create an empty offline sale.");
        }

        return DB::transaction(function () use ($saleData, $itemsData, $staffUser) {
            $currency = 'NPR';
            $rateToNpr = 1.0;

            $subtotal = 0.00;
            $totalCost = 0.00;
            $totalProfit = 0.00;
            $hasRealizedCost = true;
            $processedItems = [];

            // Deterministic row locking in numerical order to prevent deadlocks under concurrency
            $productIds = collect($itemsData)->pluck('product_id')->filter()->unique()->sort()->values()->all();
            $lockedProducts = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $variantIds = collect($itemsData)->pluck('variant_id')->filter()->unique()->sort()->values()->all();
            $lockedVariants = !empty($variantIds)
                ? ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            // 1. Process and validate line items
            foreach ($itemsData as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                $product = $lockedProducts->get($productId) ?? Product::findOrFail($productId);
                $variant = $variantId ? ($lockedVariants->get($variantId) ?? ProductVariant::findOrFail($variantId)) : null;

                // Check stock availability if overselling is disallowed
                $availableStock = $variant ? (int)$variant->stock_quantity : (int)($product->quantity ?? 0);
                $allowOverselling = app(\App\Services\Settings\SettingsService::class)->getBoolean('pos', 'allow_overselling', false);
                if (!$allowOverselling && $availableStock < $qty) {
                    $pName = $variant ? "{$product->name} ({$variant->color}/{$variant->size})" : $product->name;
                    throw new \RuntimeException("Insufficient stock for {$pName}. Available: {$availableStock}, Requested: {$qty}.");
                }

                // Reference website retail price in NPR
                $websitePrice = $variant && (float)$variant->price > 0
                    ? (float)$variant->price
                    : (float)($product->price ?? 0.0);

                // Actual offline selling price (defaults to website price if not explicitly adjusted)
                $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : $websitePrice;
                $itemDiscount = (float)($item['discount_amount'] ?? 0.0);
                $itemTotal = max(0.00, ($unitPrice * $qty) - $itemDiscount);
                $subtotal += ($unitPrice * $qty);

                // Cost Catalog Integration: lookup true landed cost in NPR
                $sku = $variant ? $variant->sku : $product->sku;
                $costLookup = $this->lookupLandedCost($sku, $product->id);

                $unitCost = $costLookup['unit_cost_npr'];
                $costType = $costLookup['cost_type'];
                if ($costType !== 'realized') {
                    $hasRealizedCost = false;
                }

                $itemTotalCost = round($unitCost * $qty, 4);

                // Revenue and profit calculation in npr base
                $unitProfit = round(($unitPrice * $rateToNpr) - $unitCost, 4);
                $itemTotalProfit = round($unitProfit * $qty, 4);
                $itemMargin = ($unitPrice * $rateToNpr) > 0 ? round(($unitProfit / ($unitPrice * $rateToNpr)) * 100, 2) : 0.0;

                $totalCost += $itemTotalCost;
                $totalProfit += $itemTotalProfit;

                $chosenSize = !empty($item['size']) ? $item['size'] : ($variant ? $variant->size : null);

                $processedItems[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant ? $variant->id : null,
                    'product_name' => $product->name,
                    'sku' => $sku,
                    'color' => $variant ? $variant->color : null,
                    'size' => $chosenSize,
                    'quantity' => $qty,
                    'website_price' => $websitePrice,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'total_price' => $itemTotal,
                    'unit_cost_npr' => $unitCost,
                    'total_cost_npr' => $itemTotalCost,
                    'unit_profit_npr' => $unitProfit,
                    'total_profit_npr' => $itemTotalProfit,
                    'margin_percentage' => $itemMargin,
                    'cost_type' => $costType,
                    'notes' => $item['notes'] ?? null,
                    'variant_model' => $variant,
                    'product_model' => $product,
                ];
            }

            // 2. Global Discount, Shipping & Overall Totals
            $globalDiscount = (float)($saleData['discount_amount'] ?? 0.00);
            if ($globalDiscount > 0 && $subtotal > 0 && empty($saleData['override_authorized'])) {
                $maxDiscountPct = app(\App\Services\Settings\SettingsService::class)->getDecimal('pos', 'max_pos_discount_percentage', 20.0);
                $appliedDiscountPct = ($globalDiscount / $subtotal) * 100;
                if ($appliedDiscountPct > ($maxDiscountPct + 0.01)) {
                    throw new \RuntimeException("POS discount (" . number_format($appliedDiscountPct, 1) . "%) exceeds maximum allowed limit of {$maxDiscountPct}%. Supervisor authorization required.");
                }
            }
            $shippingAmount = (float)($saleData['shipping_amount'] ?? 0.00);
            $discountedSubtotal = max(0.00, $subtotal - $globalDiscount);
            $totalAmount = $discountedSubtotal + $shippingAmount;

            $overallProfit = max(0.00, round($totalProfit - ($globalDiscount * $rateToNpr), 2));
            $overallMargin = ($discountedSubtotal * $rateToNpr) > 0 ? round(($overallProfit / ($discountedSubtotal * $rateToNpr)) * 100, 2) : 0.0;

            // 3. Create OfflineSale Record
            $saleNumber = OfflineSale::generateNextSaleNumber();
            $rawPhone = $saleData['customer_phone'] ?? null;
            $customerPhone = \App\Services\Customer\CustomerService::normalizePhone($rawPhone);
            $customerEmail = !empty($saleData['customer_email']) ? strtolower(trim($saleData['customer_email'])) : null;
            $customerName = trim($saleData['customer_name'] ?? '') ?: 'Walk-in Customer';
            $userId = $saleData['user_id'] ?? null;

            // Contact number is the authoritative primary link key to customer
            if ($customerPhone) {
                try {
                    $customerUser = app(\App\Services\Customer\CustomerService::class)->resolveOrCreateCustomer(
                        $customerName,
                        $customerPhone,
                        $customerEmail
                    );
                    $userId = $customerUser->id;
                    if (($customerName === 'Walk-in Customer' || empty($customerName)) && !empty($customerUser->name)) {
                        $customerName = $customerUser->name;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Customer resolution by phone failed: " . $e->getMessage());
                }
            } elseif ($userId) {
                $existingUser = User::find($userId);
                if ($existingUser) {
                    if ($customerName === 'Walk-in Customer' && !empty($existingUser->name)) {
                        $customerName = $existingUser->name;
                    }
                    $customerPhone = $customerPhone ?: \App\Services\Customer\CustomerService::normalizePhone($existingUser->phone);
                    $customerEmail = $customerEmail ?: $existingUser->email;
                }
            }

            $sale = OfflineSale::create([
                'sale_number' => $saleNumber,
                'user_id' => $userId,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'warehouse_id' => $saleData['warehouse_id'] ?? null,
                'cash_received' => isset($saleData['cash_received']) ? (float)$saleData['cash_received'] : null,
                'change_given' => isset($saleData['change_given']) ? (float)$saleData['change_given'] : null,
                'currency' => $currency,
                'exchange_rate_to_npr' => $rateToNpr,
                'subtotal' => $subtotal,
                'discount_amount' => $globalDiscount,
                'discount_reason' => $saleData['discount_reason'] ?? null,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'total_cost_npr' => round($totalCost, 2),
                'total_profit_npr' => $overallProfit,
                'margin_percentage' => $overallMargin,
                'profit_status' => $hasRealizedCost ? 'realized' : 'estimated',
                'payment_method' => $saleData['payment_method'] ?? 'cash',
                'sales_channel' => $saleData['sales_channel'] ?? 'physical',
                'status' => 'completed',
                'sold_at' => $saleData['sold_at'] ?? now(),
                'created_by' => $staffUser?->id ?? auth()->id(),
                'staff_name' => $staffUser?->name ?? (auth()->user()?->name ?? 'Admin'),
                'customer_notes' => $saleData['customer_notes'] ?? null,
                'internal_notes' => $saleData['internal_notes'] ?? null,
            ]);

            // 4. Save items & atomically decrement inventory stock
            foreach ($processedItems as $pItem) {
                unset($pItem['variant_model'], $pItem['product_model']);
                $pItem['offline_sale_id'] = $sale->id;
                OfflineSaleItem::create($pItem);
            }

            // Authoritative Enterprise Inventory Stock Deduction & Ledger Entry
            $autoDeduct = app(\App\Services\Settings\SettingsService::class)->getBoolean('pos', 'auto_pos_inventory_deduction', true);
            if ($autoDeduct) {
                app(\App\Services\Inventory\InventoryService::class)->deductPosSale($sale, $staffUser, $saleData['warehouse_id'] ?? null);
            }

            // Accounting Integration: auto-post balanced double-entry voucher
            if (app(\App\Services\Settings\SettingsService::class)->getBoolean('accounting', 'auto_post_journals', true)) {
                try {
                    app(\App\Services\Accounting\AccountingService::class)->recordOfflineSale($sale);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Accounting auto-posting skipped for offline sale #{$sale->sale_number}: " . $e->getMessage());
                }
            }

            return $sale;
        });
    }

    /**
     * Look up true landed cost from official product costing or return estimated hnpristic in NPR.
     */
    public function lookupLandedCost(string $sku, int $productId): array
    {
        $product = Product::find($productId);
        $variant = ProductVariant::where('sku', $sku)->where('product_id', $productId)->first();

        $variantCost = $variant ? (float)($variant->cost_price ?: ($variant->cost_price_npr ?: 0)) : 0.0;
        if ($variant && $variantCost > 0) {
            return [
                'unit_cost_npr' => $variantCost,
                'cost_type' => 'realized',
            ];
        }

        $productCost = $product ? (float)($product->cost_price ?: ($product->cost_price_npr ?: 0)) : 0.0;
        if ($product && $productCost > 0) {
            return [
                'unit_cost_npr' => $productCost,
                'cost_type' => 'realized',
            ];
        }

        // Fallback: conservative 45% of retail price as estimated wholesale cost in NPR
        $refPrice = $product ? (float)($product->price ?: 0.0) : 0.0;
        $estCost = round($refPrice * 0.45, 2);

        return [
            'unit_cost_npr' => $estCost,
            'cost_type' => 'estimated',
        ];
    }

    /**
     * Safely void an offline sale, log audit record, and restock inventory.
     */
    public function voidSale(OfflineSale $sale, string $reason, ?User $staffUser = null, bool $restock = true): void
    {
        if ($sale->status === 'voided') {
            throw new \RuntimeException("Sale #{$sale->sale_number} is already voided.");
        }

        DB::transaction(function () use ($sale, $reason, $staffUser, $restock) {
            $sale->load(['items.variant', 'items.product']);

            // 1. Restock inventory if requested
            if ($restock) {
                try {
                    app(\App\Services\Inventory\InventoryService::class)->restorePosSale($sale, $reason, $staffUser);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Enterprise inventory restock error for voided sale #{$sale->sale_number}: " . $e->getMessage());
                }
            }

            // 2. Log void audit trail
            OfflineSaleVoidLog::create([
                'offline_sale_id' => $sale->id,
                'voided_by_user_id' => $staffUser?->id ?? auth()->id(),
                'voided_by_name' => $staffUser?->name ?? (auth()->user()?->name ?? 'Admin'),
                'reason' => trim($reason) ?: 'Administrative correction',
                'restocked' => $restock,
                'snapshot_data' => [
                    'sale_number' => $sale->sale_number,
                    'total_amount' => $sale->total_amount,
                    'currency' => $sale->currency,
                    'items_count' => $sale->items->count(),
                    'customer_name' => $sale->customer_name,
                    'payment_method' => $sale->payment_method,
                    'sales_channel' => $sale->sales_channel,
                ],
            ]);

            // 3. Update sale status
            $sale->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $staffUser?->id ?? auth()->id(),
            ]);

            // 4. Accounting Reversal: Reverse posted journal voucher under Nepal Accounting Standards
            try {
                $voucher = \App\Models\Accounting\JournalEntry::where('reference_type', 'offline_sale')
                    ->where('reference_id', $sale->id)
                    ->where('status', 'posted')
                    ->first();
                if ($voucher) {
                    app(\App\Services\Accounting\AccountingService::class)->reverseJournalEntry($voucher, "Voided POS sale #{$sale->sale_number}: {$reason}", $staffUser);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Accounting reversal skipped for voided sale #{$sale->sale_number}: " . $e->getMessage());
            }
        });
    }

    /**
     * Calculate comprehensive Dashboard KPIs in NPR.
     */
    public function getDashboardMetrics(): array
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        $activeSales = OfflineSale::where('status', 'completed');

        // Today
        $todaySales = (clone $activeSales)->where('sold_at', '>=', $todayStart)->get();
        $todayRevenue = (float)$todaySales->sum('total_amount');
        $todayProfit = (float)$todaySales->sum('total_profit_npr');
        $todayUnits = (int)OfflineSaleItem::whereIn('offline_sale_id', $todaySales->pluck('id'))->sum('quantity');
        $todayMargin = $todayRevenue > 0 ? round((($todayProfit / $todayRevenue) * 100), 1) : 0.0;

        // This Month
        $monthSales = (clone $activeSales)->where('sold_at', '>=', $monthStart)->get();
        $monthRevenue = (float)$monthSales->sum('total_amount');
        $monthProfit = (float)$monthSales->sum('total_profit_npr');
        $monthUnits = (int)OfflineSaleItem::whereIn('offline_sale_id', $monthSales->pluck('id'))->sum('quantity');
        $monthMargin = $monthRevenue > 0 ? round((($monthProfit / $monthRevenue) * 100), 1) : 0.0;

        // Payment Method Breakdown
        $allActive = (clone $activeSales)->get();
        $payments = [
            'cash' => ['label' => 'Cash', 'count' => 0, 'amount' => 0.0],
            'card' => ['label' => 'Card Terminal (POS)', 'count' => 0, 'amount' => 0.0],
            'esewa' => ['label' => 'eSewa / Digital Wallet', 'count' => 0, 'amount' => 0.0],
            'khalti' => ['label' => 'Khalti / Fonepay QR', 'count' => 0, 'amount' => 0.0],
            'bank_transfer' => ['label' => 'Bank Wire / ConnectIPS', 'count' => 0, 'amount' => 0.0],
            'other' => ['label' => 'Other', 'count' => 0, 'amount' => 0.0],
        ];

        foreach ($allActive as $s) {
            $m = $s->payment_method;
            if (!isset($payments[$m])) {
                $payments[$m] = ['label' => ucfirst(str_replace('_', ' ', (string)$m)), 'count' => 0, 'amount' => 0.0];
            }
            $payments[$m]['count']++;
            $payments[$m]['amount'] += (float)$s->total_amount;
        }

        // Channel Breakdown
        $channels = [
            'physical' => ['label' => 'Direct In-Person', 'count' => 0, 'amount' => 0.0],
            'instagram' => ['label' => 'Instagram DM', 'count' => 0, 'amount' => 0.0],
            'whatsapp' => ['label' => 'WhatsApp Sale', 'count' => 0, 'amount' => 0.0],
            'showroom' => ['label' => 'Laijau Showroom', 'count' => 0, 'amount' => 0.0],
            'event' => ['label' => 'Pop-up & Event', 'count' => 0, 'amount' => 0.0],
            'wholesale' => ['label' => 'Wholesale', 'count' => 0, 'amount' => 0.0],
            'other' => ['label' => 'Other Channels', 'count' => 0, 'amount' => 0.0],
        ];

        foreach ($allActive as $s) {
            $c = $s->sales_channel;
            if (!isset($channels[$c])) {
                $channels[$c] = ['label' => ucfirst(str_replace('_', ' ', (string)$c)), 'count' => 0, 'amount' => 0.0];
            }
            $channels[$c]['count']++;
            $channels[$c]['amount'] += (float)$s->total_amount;
        }

        return [
            'today' => [
                'count' => $todaySales->count(),
                'revenue' => $todayRevenue,
                'profit_npr' => $todayProfit,
                'units' => $todayUnits,
                'margin' => $todayMargin,
            ],
            'month' => [
                'count' => $monthSales->count(),
                'revenue' => $monthRevenue,
                'profit_npr' => $monthProfit,
                'units' => $monthUnits,
                'margin' => $monthMargin,
            ],
            'payments' => $payments,
            'channels' => $channels,
            'total_lifetime_count' => $allActive->count(),
            'total_lifetime_revenue' => (float)$allActive->sum('total_amount'),
            'total_lifetime_profit_npr' => (float)$allActive->sum('total_profit_npr'),
        ];
    }

    /**
     * Aggregate offline sales performance per product in NPR.
     */
    public function getProductPerformance(): array
    {
        $items = OfflineSaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))
            ->with(['product', 'variant'])
            ->get();

        $grouped = $items->groupBy('product_id');
        $performance = [];

        foreach ($grouped as $productId => $prodItems) {
            $product = $prodItems->first()->product;
            if (!$product) continue;

            $units = $prodItems->sum('quantity');
            $revenue = $prodItems->sum('total_price');
            $cost = $prodItems->sum('total_cost_npr');
            $profit = $prodItems->sum('total_profit_npr');
            $avgPrice = $units > 0 ? round($revenue / $units, 2) : 0.0;
            $margin = $revenue > 0 ? round((($profit / $revenue) * 100), 1) : 0.0;

            $performance[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'image' => $product->featured_image,
                'website_price_npr' => (float)($product->price ?? 0.0),
                'offline_units' => (int)$units,
                'units_sold' => (int)$units,
                'offline_revenue' => (float)$revenue,
                'revenue_npr' => (float)$revenue,
                'offline_cost_npr' => (float)$cost,
                'offline_profit_npr' => (float)$profit,
                'avg_selling_price_npr' => $avgPrice,
                'margin_percentage' => $margin,
                'margin_pct' => $margin,
            ];
        }

        usort($performance, fn($a, $b) => $b['offline_revenue'] <=> $a['offline_revenue']);

        return $performance;
    }
}
