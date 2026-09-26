<?php

namespace App\Services;

use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\OfflineSaleVoidLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Carbon;
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
                'pos_session_id' => $saleData['pos_session_id'] ?? null,
                'pos_station_id' => $saleData['pos_station_id'] ?? null,
                'business_date' => $saleData['business_date'] ?? Carbon::now('Asia/Kathmandu')->toDateString(),
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
                'sold_at' => $saleData['sold_at'] ?? Carbon::now('Asia/Kathmandu'),
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
     * Calculate comprehensive POS Dashboard KPIs and deep-dive analytics in NPR.
     *
     * @param array $filters [
     *   'period' => 'today'|'yesterday'|'week'|'month'|'last_month'|'year'|'all'|'custom',
     *   'start_date' => 'Y-m-d'|null,
     *   'end_date' => 'Y-m-d'|null,
     *   'payment_method' => string|null,
     *   'staff_name' => string|null,
     *   'warehouse_id' => int|null,
     * ]
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $now = Carbon::now('Asia/Kathmandu');
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $activeSalesBase = OfflineSale::where('status', 'completed');

        // Backward-compatible metrics for today & month
        $todaySales = (clone $activeSalesBase)->whereBetween('sold_at', [$todayStart, $todayEnd])->get();
        $todayRevenue = (float)$todaySales->sum('total_amount');
        $todayProfit = (float)$todaySales->sum('total_profit_npr');
        $todayUnits = (int)OfflineSaleItem::whereIn('offline_sale_id', $todaySales->pluck('id'))->sum('quantity');
        $todayMargin = $todayRevenue > 0 ? round((($todayProfit / $todayRevenue) * 100), 1) : 0.0;

        $monthSales = (clone $activeSalesBase)->whereBetween('sold_at', [$monthStart, $monthEnd])->get();
        $monthRevenue = (float)$monthSales->sum('total_amount');
        $monthProfit = (float)$monthSales->sum('total_profit_npr');
        $monthUnits = (int)OfflineSaleItem::whereIn('offline_sale_id', $monthSales->pluck('id'))->sum('quantity');
        $monthMargin = $monthRevenue > 0 ? round((($monthProfit / $monthRevenue) * 100), 1) : 0.0;

        $allActive = (clone $activeSalesBase)->get();

        // 1. Resolve Active Period Range
        $period = $filters['period'] ?? 'today';
        $startDate = null;
        $endDate = null;
        $prevStartDate = null;
        $prevEndDate = null;
        $periodLabel = 'Today';

        switch ($period) {
            case 'yesterday':
                $startDate = $now->copy()->subDay()->startOfDay();
                $endDate = $now->copy()->subDay()->endOfDay();
                $prevStartDate = $now->copy()->subDays(2)->startOfDay();
                $prevEndDate = $now->copy()->subDays(2)->endOfDay();
                $periodLabel = 'Yesterday (' . $startDate->format('M d, Y') . ')';
                break;
            case 'week':
                $startDate = $now->copy()->subDays(6)->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $prevStartDate = $now->copy()->subDays(13)->startOfDay();
                $prevEndDate = $now->copy()->subDays(7)->endOfDay();
                $periodLabel = 'Last 7 Days (' . $startDate->format('M d') . ' - ' . $endDate->format('M d') . ')';
                break;
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $prevStartDate = $now->copy()->subMonth()->startOfMonth();
                $prevEndDate = $now->copy()->subMonth()->endOfMonth();
                $periodLabel = 'This Month (' . $startDate->format('F Y') . ')';
                break;
            case 'last_month':
                $startDate = $now->copy()->subMonth()->startOfMonth();
                $endDate = $now->copy()->subMonth()->endOfMonth();
                $prevStartDate = $now->copy()->subMonths(2)->startOfMonth();
                $prevEndDate = $now->copy()->subMonths(2)->endOfMonth();
                $periodLabel = 'Last Month (' . $startDate->format('F Y') . ')';
                break;
            case 'year':
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
                $prevStartDate = $now->copy()->subYear()->startOfYear();
                $prevEndDate = $now->copy()->subYear()->endOfYear();
                $periodLabel = 'This Year (' . $startDate->format('Y') . ')';
                break;
            case 'all':
                $startDate = null;
                $endDate = null;
                $periodLabel = 'All Time (Full History)';
                break;
            case 'custom':
                if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                    $startDate = Carbon::parse($filters['start_date'], 'Asia/Kathmandu')->startOfDay();
                    $endDate = Carbon::parse($filters['end_date'], 'Asia/Kathmandu')->endOfDay();
                    $diffDays = $startDate->diffInDays($endDate) + 1;
                    $prevStartDate = $startDate->copy()->subDays($diffDays);
                    $prevEndDate = $startDate->copy()->subSecond();
                    $periodLabel = $startDate->format('M d, Y') . ' — ' . $endDate->format('M d, Y');
                } else {
                    $startDate = $todayStart;
                    $endDate = $todayEnd;
                    $periodLabel = 'Today (' . $now->format('M d, Y') . ')';
                }
                break;
            case 'today':
            default:
                $startDate = $todayStart;
                $endDate = $todayEnd;
                $prevStartDate = $now->copy()->subDay()->startOfDay();
                $prevEndDate = $now->copy()->subDay()->endOfDay();
                $periodLabel = 'Today (' . $now->format('M d, Y') . ')';
                break;
        }

        // 2. Query Completed Sales for the Active Period
        $filteredQuery = OfflineSale::where('status', 'completed');
        if ($startDate && $endDate) {
            $filteredQuery->whereBetween('sold_at', [$startDate, $endDate]);
        }
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $filteredQuery->where('payment_method', $filters['payment_method']);
        }
        if (!empty($filters['staff_name']) && $filters['staff_name'] !== 'all') {
            $filteredQuery->where('staff_name', $filters['staff_name']);
        }
        if (!empty($filters['pos_session_id'])) {
            $filteredQuery->where('pos_session_id', $filters['pos_session_id']);
        }
        if (!empty($filters['pos_station_id'])) {
            $filteredQuery->where(function ($q) use ($filters) {
                $q->where('pos_station_id', $filters['pos_station_id'])
                  ->orWhere('warehouse_id', function ($sub) use ($filters) {
                      $sub->select('warehouse_id')->from('pos_stations')->where('id', $filters['pos_station_id']);
                  });
            });
        }
        if (!empty($filters['warehouse_id'])) {
            $filteredQuery->where('warehouse_id', $filters['warehouse_id']);
        }

        $periodSales = $filteredQuery->with(['items'])->get();
        $saleIds = $periodSales->pluck('id');

        // 3. Comparison Query for Growth Indicators
        $prevRevenue = 0.0;
        $prevCount = 0;
        if ($prevStartDate && $prevEndDate) {
            $prevQuery = OfflineSale::where('status', 'completed')->whereBetween('sold_at', [$prevStartDate, $prevEndDate]);
            if (!empty($filters['warehouse_id'])) {
                $prevQuery->where('warehouse_id', $filters['warehouse_id']);
            }
            if (!empty($filters['pos_station_id'])) {
                $prevQuery->where('pos_station_id', $filters['pos_station_id']);
            }
            if (!empty($filters['staff_name']) && $filters['staff_name'] !== 'all') {
                $prevQuery->where('staff_name', $filters['staff_name']);
            }
            if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
                $prevQuery->where('payment_method', $filters['payment_method']);
            }
            $prevRevenue = (float)$prevQuery->sum('total_amount');
            $prevCount = (int)$prevQuery->count();
        }

        // 4. Core Volume & Revenue KPIs
        $revenue = (float)$periodSales->sum('total_amount');
        $subtotal = (float)$periodSales->sum('subtotal');
        $discountTotal = (float)$periodSales->sum('discount_amount');
        $profitTotal = (float)$periodSales->sum('total_profit_npr');
        $count = $periodSales->count();
        $unitsSold = (int)OfflineSaleItem::whereIn('offline_sale_id', $saleIds)->sum('quantity');

        $aov = $count > 0 ? round($revenue / $count, 2) : 0.0;
        $upt = $count > 0 ? round($unitsSold / $count, 1) : 0.0;
        $marginPct = $revenue > 0 ? round(($profitTotal / $revenue) * 100, 1) : 0.0;
        $grossValue = $revenue + $discountTotal;
        $discountRatePct = $grossValue > 0 ? round(($discountTotal / $grossValue) * 100, 1) : 0.0;
        $avgDiscount = $count > 0 ? round($discountTotal / $count, 2) : 0.0;

        $revenueGrowthPct = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1) : null;
        $countGrowthPct = $prevCount > 0 ? round((($count - $prevCount) / $prevCount) * 100, 1) : null;

        // Voided transactions in this period
        $voidedQuery = OfflineSale::where('status', 'voided');
        if ($startDate && $endDate) {
            $voidedQuery->whereBetween('voided_at', [$startDate, $endDate]);
        }
        if (!empty($filters['pos_session_id'])) {
            $voidedQuery->where('pos_session_id', $filters['pos_session_id']);
        }
        if (!empty($filters['pos_station_id'])) {
            $voidedQuery->where('pos_station_id', $filters['pos_station_id']);
        }
        $voidedCount = (int)$voidedQuery->count();
        $voidedAmount = (float)$voidedQuery->sum('total_amount');

        // 5. DEEP-DIVE PAYMENT ANALYTICS ("How it was paid")
        $pureCashSales = $periodSales->where('payment_method', 'cash');
        $pureCashRevenue = (float)$pureCashSales->sum('total_amount');
        $pureCashCount = $pureCashSales->count();

        $splitSales = $periodSales->where('payment_method', 'split');
        $splitCashRevenue = (float)$splitSales->sum('cash_received');
        $splitDigitalRevenue = (float)$splitSales->sum(fn($s) => max(0.0, (float)$s->total_amount - (float)$s->cash_received));
        $splitCount = $splitSales->count();

        $pureDigitalSales = $periodSales->whereNotIn('payment_method', ['cash', 'split']);
        $pureDigitalRevenue = (float)$pureDigitalSales->sum('total_amount');
        $pureDigitalCount = $pureDigitalSales->count();

        $physicalCashTotal = round($pureCashRevenue + $splitCashRevenue, 2);
        $digitalTotal = round($pureDigitalRevenue + $splitDigitalRevenue, 2);
        $physicalCashPct = $revenue > 0 ? round(($physicalCashTotal / $revenue) * 100, 1) : 0.0;
        $digitalPct = $revenue > 0 ? round(($digitalTotal / $revenue) * 100, 1) : 0.0;

        // Session Information Resolution
        $sessionDetails = null;
        $openingBalance = 0.0;
        if (!empty($filters['pos_session_id'])) {
            $posSession = \App\Models\Pos\PosSession::find($filters['pos_session_id']);
            if ($posSession) {
                $openingBalance = (float)$posSession->opening_balance;
                $sessionDetails = [
                    'id' => $posSession->id,
                    'terminal_code' => $posSession->terminal_code,
                    'terminal_name' => $posSession->terminal_name,
                    'showroom_name' => $posSession->showroom_name,
                    'business_date' => $posSession->business_date->format('M d, Y'),
                    'status' => $posSession->status,
                    'opening_balance' => $openingBalance,
                    'opened_by_name' => $posSession->opened_by_name,
                    'opened_at' => $posSession->opened_at ? Carbon::parse($posSession->opened_at)->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') : 'N/A',
                    'expected_cash' => (float)$posSession->expected_cash,
                    'closing_cash_counted' => $posSession->closing_cash_counted !== null ? (float)$posSession->closing_cash_counted : null,
                    'cash_variance' => (float)$posSession->cash_variance,
                    'closed_by_name' => $posSession->closed_by_name,
                    'closed_at' => $posSession->closed_at ? Carbon::parse($posSession->closed_at)->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') : null,
                    'manager_name' => $posSession->manager_name,
                ];
            }
        } elseif (!empty($filters['pos_station_id'])) {
            $posSession = \App\Models\Pos\PosSession::where('pos_station_id', $filters['pos_station_id'])
                ->where('business_date', Carbon::now('Asia/Kathmandu')->toDateString())
                ->first();
            if ($posSession) {
                $openingBalance = (float)$posSession->opening_balance;
                $sessionDetails = [
                    'id' => $posSession->id,
                    'terminal_code' => $posSession->terminal_code,
                    'terminal_name' => $posSession->terminal_name,
                    'showroom_name' => $posSession->showroom_name,
                    'business_date' => $posSession->business_date->format('M d, Y'),
                    'status' => $posSession->status,
                    'opening_balance' => $openingBalance,
                    'opened_by_name' => $posSession->opened_by_name,
                    'opened_at' => $posSession->opened_at ? Carbon::parse($posSession->opened_at)->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') : 'N/A',
                    'expected_cash' => (float)$posSession->expected_cash,
                    'closing_cash_counted' => $posSession->closing_cash_counted !== null ? (float)$posSession->closing_cash_counted : null,
                    'cash_variance' => (float)$posSession->cash_variance,
                    'closed_by_name' => $posSession->closed_by_name,
                    'closed_at' => $posSession->closed_at ? Carbon::parse($posSession->closed_at)->timezone('Asia/Kathmandu')->format('M d, Y · h:i A') : null,
                    'manager_name' => $posSession->manager_name,
                ];
            }
        }

        // Drawer Reconciliation (Till Balancing with Opening Float)
        $cashTendered = (float)$periodSales->sum('cash_received');
        $changeReturned = (float)$periodSales->sum('change_given');
        $netCashInDrawer = round($openingBalance + $physicalCashTotal, 2);
        $cashTransCount = $pureCashCount + $splitCount;
        $avgCashTicket = $cashTransCount > 0 ? round($physicalCashTotal / $cashTransCount, 2) : 0.0;

        // Comprehensive Payment Breakdown List
        $methodConfigs = [
            'cash' => ['label' => 'Cash', 'icon' => '💵', 'badge_class' => 'lj-badge-emerald', 'color' => '#059669', 'desc' => 'Physical Notes in Till'],
            'fonepay' => ['label' => 'Fonepay QR', 'icon' => '📱', 'badge_class' => 'lj-badge-rose', 'color' => '#E11D48', 'desc' => 'Instant Dynamic QR / Merchant App'],
            'split' => ['label' => 'Split Payment (Cash + QR)', 'icon' => '⚡', 'badge_class' => 'lj-badge-amber', 'color' => '#D97706', 'desc' => 'Partially Cash, Partially Digital'],
            'esewa' => ['label' => 'eSewa QR', 'icon' => '🟢', 'badge_class' => 'lj-badge-green', 'color' => '#10B981', 'desc' => 'Digital Wallet Scan & Pay'],
            'khalti' => ['label' => 'Khalti QR', 'icon' => '🟣', 'badge_class' => 'lj-badge-purple', 'color' => '#8B5CF6', 'desc' => 'Khalti Digital Wallet QR'],
            'card' => ['label' => 'Card Terminal (POS)', 'icon' => '💳', 'badge_class' => 'lj-badge-blue', 'color' => '#2563EB', 'desc' => 'Bank Debit / Credit Card Swipe'],
            'bank_transfer' => ['label' => 'Bank Wire / ConnectIPS', 'icon' => '🏦', 'badge_class' => 'lj-badge-indigo', 'color' => '#4F46E5', 'desc' => 'Direct Account Settlement'],
            'other' => ['label' => 'Other Payment', 'icon' => '🏷️', 'badge_class' => 'lj-badge-slate', 'color' => '#64748B', 'desc' => 'Gift Card / Cheque / Other'],
        ];

        $methodsBreakdown = [];
        foreach ($methodConfigs as $mKey => $cfg) {
            $mSales = $periodSales->where('payment_method', $mKey);
            $mCount = $mSales->count();
            $mAmount = (float)$mSales->sum('total_amount');
            if ($mCount > 0 || in_array($mKey, ['cash', 'fonepay', 'split', 'esewa', 'card', 'bank_transfer'], true)) {
                $methodsBreakdown[$mKey] = [
                    'key' => $mKey,
                    'label' => $cfg['label'],
                    'icon' => $cfg['icon'],
                    'badge_class' => $cfg['badge_class'],
                    'color' => $cfg['color'],
                    'desc' => $cfg['desc'],
                    'count' => $mCount,
                    'count_pct' => $count > 0 ? round(($mCount / $count) * 100, 1) : 0.0,
                    'amount' => $mAmount,
                    'amount_pct' => $revenue > 0 ? round(($mAmount / $revenue) * 100, 1) : 0.0,
                    'avg_ticket' => $mCount > 0 ? round($mAmount / $mCount, 2) : 0.0,
                ];
            }
        }

        foreach ($periodSales as $s) {
            $mKey = $s->payment_method ?: 'other';
            if (!isset($methodsBreakdown[$mKey])) {
                $mSales = $periodSales->where('payment_method', $mKey);
                $mCount = $mSales->count();
                $mAmount = (float)$mSales->sum('total_amount');
                $methodsBreakdown[$mKey] = [
                    'key' => $mKey,
                    'label' => ucfirst(str_replace('_', ' ', $mKey)),
                    'icon' => '🏷️',
                    'badge_class' => 'lj-badge-slate',
                    'color' => '#64748B',
                    'desc' => 'Custom Payment Method',
                    'count' => $mCount,
                    'count_pct' => $count > 0 ? round(($mCount / $count) * 100, 1) : 0.0,
                    'amount' => $mAmount,
                    'amount_pct' => $revenue > 0 ? round(($mAmount / $revenue) * 100, 1) : 0.0,
                    'avg_ticket' => $mCount > 0 ? round($mAmount / $mCount, 2) : 0.0,
                ];
            }
        }
        uasort($methodsBreakdown, fn($a, $b) => $b['amount'] <=> $a['amount']);

        // 6. HOURLY SALES DISTRIBUTION (Peak Showroom Traffic)
        $hourly = [];
        for ($h = 8; $h <= 21; $h++) {
            $hourKey = sprintf('%02d', $h);
            $label = Carbon::createFromTime($h, 0, 0, 'Asia/Kathmandu')->format('g A');
            $hSales = $periodSales->filter(function ($s) use ($hourKey) {
                return $s->sold_at && Carbon::parse($s->sold_at)->timezone('Asia/Kathmandu')->format('H') === $hourKey;
            });
            $hRev = (float)$hSales->sum('total_amount');
            $hCount = $hSales->count();
            $hourly[$hourKey] = [
                'hour' => $hourKey,
                'label' => $label,
                'revenue' => $hRev,
                'count' => $hCount,
                'pct_of_peak' => 0.0,
            ];
        }

        $maxHourRev = max(1.0, max(array_column($hourly, 'revenue')));
        $peakHour = null;
        $peakRev = 0.0;
        $peakOrders = 0;
        foreach ($hourly as $k => &$hItem) {
            $hItem['pct_of_peak'] = round(($hItem['revenue'] / $maxHourRev) * 100, 1);
            if ($hItem['revenue'] > $peakRev) {
                $peakRev = $hItem['revenue'];
                $peakHour = $hItem['label'];
                $peakOrders = $hItem['count'];
            }
        }
        unset($hItem);

        // 7. STAFF PERFORMANCE BREAKDOWN
        $staffPerformance = [];
        $staffGrouped = $periodSales->groupBy(fn($s) => $s->staff_name ?: 'Showroom Cashier');
        foreach ($staffGrouped as $sName => $sSales) {
            $sCount = $sSales->count();
            $sRev = (float)$sSales->sum('total_amount');
            $sDiscounts = (float)$sSales->sum('discount_amount');
            $topMethod = $sSales->groupBy('payment_method')->sortByDesc(fn($grp) => $grp->count())->keys()->first() ?: 'cash';
            $staffPerformance[] = [
                'staff_name' => $sName,
                'sales_count' => $sCount,
                'total_revenue' => $sRev,
                'revenue_pct' => $revenue > 0 ? round(($sRev / $revenue) * 100, 1) : 0.0,
                'avg_ticket' => $sCount > 0 ? round($sRev / $sCount, 2) : 0.0,
                'total_discount' => $sDiscounts,
                'top_payment_method' => ucfirst(str_replace('_', ' ', $topMethod)),
            ];
        }
        usort($staffPerformance, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        // 8. SALES CHANNEL BREAKDOWN
        $channelConfigs = [
            'showroom_pos' => 'Showroom Walk-in',
            'physical' => 'Direct In-Person',
            'whatsapp' => 'WhatsApp Order',
            'instagram' => 'Instagram DM',
            'event' => 'Pop-up & Event',
            'wholesale' => 'Wholesale',
            'other' => 'Other Channels',
        ];
        $channelAnalytics = [];
        $channelGrouped = $periodSales->groupBy('sales_channel');
        foreach ($channelGrouped as $cKey => $cSales) {
            $cCount = $cSales->count();
            $cAmount = (float)$cSales->sum('total_amount');
            $channelAnalytics[] = [
                'key' => $cKey,
                'label' => $channelConfigs[$cKey] ?? ucfirst(str_replace('_', ' ', (string)$cKey)),
                'count' => $cCount,
                'count_pct' => $count > 0 ? round(($cCount / $count) * 100, 1) : 0.0,
                'amount' => $cAmount,
                'amount_pct' => $revenue > 0 ? round(($cAmount / $revenue) * 100, 1) : 0.0,
            ];
        }
        usort($channelAnalytics, fn($a, $b) => $b['amount'] <=> $a['amount']);

        // 9. CUSTOMER INSIGHTS
        $walkinSales = $periodSales->filter(fn($s) => empty($s->user_id) && ($s->customer_name === 'Walk-in Customer' || empty($s->customer_phone)));
        $registeredSales = $periodSales->filter(fn($s) => !empty($s->user_id) || (!empty($s->customer_phone) && $s->customer_name !== 'Walk-in Customer'));
        $customerInsights = [
            'walkin_count' => $walkinSales->count(),
            'walkin_revenue' => (float)$walkinSales->sum('total_amount'),
            'registered_count' => $registeredSales->count(),
            'registered_revenue' => (float)$registeredSales->sum('total_amount'),
            'unique_phones_captured' => $periodSales->whereNotNull('customer_phone')->where('customer_phone', '!=', '')->pluck('customer_phone')->unique()->count(),
        ];

        // 10. TOP 8 BEST-SELLING PRODUCTS IN PERIOD
        $topProducts = [];
        if ($saleIds->isNotEmpty()) {
            $topItemsQuery = OfflineSaleItem::whereIn('offline_sale_id', $saleIds)
                ->select(
                    'product_name',
                    'sku',
                    DB::raw('sum(quantity) as units_sold'),
                    DB::raw('sum(total_price) as revenue_npr'),
                    DB::raw('sum(total_profit_npr) as profit_npr')
                )
                ->groupBy('product_name', 'sku')
                ->orderByDesc('revenue_npr')
                ->take(8)
                ->get();

            foreach ($topItemsQuery as $item) {
                $pUnits = (int)$item->units_sold;
                $pRev = (float)$item->revenue_npr;
                $pProfit = (float)$item->profit_npr;
                $topProducts[] = [
                    'product_name' => $item->product_name ?: 'Product',
                    'sku' => $item->sku,
                    'units_sold' => $pUnits,
                    'revenue_npr' => $pRev,
                    'avg_price' => $pUnits > 0 ? round($pRev / $pUnits, 2) : 0.0,
                    'profit_npr' => $pProfit,
                    'margin_pct' => $pRev > 0 ? round(($pProfit / $pRev) * 100, 1) : 0.0,
                ];
            }
        }

        // 11. RECENT 10 TRANSACTIONS
        $recentTransactions = $periodSales->sortByDesc('sold_at')->take(10)->map(function ($s) {
            return [
                'id' => $s->id,
                'sale_number' => $s->sale_number,
                'sold_at_time' => $s->sold_at ? Carbon::parse($s->sold_at)->timezone('Asia/Kathmandu')->format('h:i A') : '',
                'sold_at_date' => $s->sold_at ? Carbon::parse($s->sold_at)->timezone('Asia/Kathmandu')->format('M d') : '',
                'customer_name' => $s->customer_name ?: 'Walk-in Customer',
                'customer_phone' => $s->customer_phone,
                'staff_name' => $s->staff_name ?: 'Cashier',
                'payment_method' => $s->payment_method,
                'items_count' => $s->items->sum('quantity'),
                'total_amount' => (float)$s->total_amount,
                'status' => $s->status,
            ];
        })->values()->toArray();

        // 12. FULL ITEMIZED SALES JOURNAL (For Day Ledger & Shift Report Printout)
        $allSalesList = $periodSales->sortBy('sold_at')->map(function ($s) use ($methodConfigs) {
            $itemsList = $s->items->map(function ($it) {
                $pName = $it->product_name ?: 'Product';
                $variantParts = array_filter([$it->color, $it->size]);
                $variantStr = !empty($variantParts) ? ' (' . implode('/', $variantParts) . ')' : '';
                return $it->quantity . 'x ' . $pName . $variantStr;
            })->values()->toArray();

            $soldAtCarbon = $s->sold_at ? Carbon::parse($s->sold_at)->timezone('Asia/Kathmandu') : null;
            $subtotalAmt = (float)$s->subtotal;
            $discAmt = (float)$s->discount_amount;
            $totAmt = (float)$s->total_amount;
            $discPct = ($subtotalAmt > 0 && $discAmt > 0) ? round(($discAmt / $subtotalAmt) * 100, 1) : 0.0;

            return [
                'id' => $s->id,
                'sale_number' => $s->sale_number,
                'sold_at_time' => $soldAtCarbon ? $soldAtCarbon->format('h:i A') : 'N/A',
                'sold_at_date' => $soldAtCarbon ? $soldAtCarbon->format('M d, Y') : 'N/A',
                'sold_at_full' => $soldAtCarbon ? $soldAtCarbon->format('M d, Y · h:i A') : 'N/A',
                'customer_name' => $s->customer_name ?: 'Walk-in Customer',
                'customer_phone' => $s->customer_phone ?: '',
                'staff_name' => $s->staff_name ?: 'Cashier',
                'payment_method' => $s->payment_method ?: 'cash',
                'payment_label' => $methodConfigs[$s->payment_method]['label'] ?? ucfirst(str_replace('_', ' ', (string)$s->payment_method)),
                'subtotal' => $subtotalAmt,
                'discount_amount' => $discAmt,
                'discount_percent' => $discPct,
                'discount_reason' => $s->discount_reason ?: '',
                'total_amount' => $totAmt,
                'cash_received' => (float)$s->cash_received,
                'change_given' => (float)$s->change_given,
                'items_count' => (int)$s->items->sum('quantity'),
                'items_summary' => !empty($itemsList) ? implode(', ', $itemsList) : '1 item',
                'status' => $s->status,
            ];
        })->values()->toArray();

        // 13. EXPLICIT DISCOUNT AUDIT (All sales where discount was granted)
        $discountedSalesList = collect($allSalesList)->filter(fn($s) => $s['discount_amount'] > 0)->values()->toArray();

        // 14. MAJOR OPERATIONAL EVENTS LOG (Everything happened on that day)
        $majorEvents = [];

        // Shift / Day Start (First Sale Punch)
        $firstSale = $periodSales->sortBy('sold_at')->first();
        if ($firstSale) {
            $firstTime = $firstSale->sold_at ? Carbon::parse($firstSale->sold_at)->timezone('Asia/Kathmandu')->format('h:i A') : 'Morning';
            $majorEvents[] = [
                'type' => 'shift_start',
                'time' => $firstTime,
                'title' => 'Shift Opened / First Sale Punch',
                'badge' => 'OPEN',
                'detail' => 'First transaction #' . $firstSale->sale_number . ' punched by ' . ($firstSale->staff_name ?: 'Cashier') . ' (Rs. ' . number_format($firstSale->total_amount, 2) . ')',
                'subdetail' => 'Customer: ' . ($firstSale->customer_name ?: 'Walk-in') . ' · Payment: ' . ($methodConfigs[$firstSale->payment_method]['label'] ?? $firstSale->payment_method),
            ];
        }

        // Peak Rush Traffic Hour
        if ($peakHour && $peakOrders > 0) {
            $majorEvents[] = [
                'type' => 'peak_rush',
                'time' => $peakHour,
                'title' => 'Peak Rush Traffic Hour (' . $peakHour . ')',
                'badge' => 'PEAK',
                'detail' => 'Highest showroom velocity: ' . $peakOrders . ' orders totaling Rs. ' . number_format($peakRev, 2),
                'subdetail' => 'Contributed ' . ($revenue > 0 ? round(($peakRev / $revenue) * 100, 1) : 0) . '% of daily gross turnover',
            ];
        }

        // Highest Value Ticket (Top Transaction)
        $highestSale = $periodSales->sortByDesc('total_amount')->first();
        if ($highestSale && $highestSale->total_amount > 0) {
            $highestTime = $highestSale->sold_at ? Carbon::parse($highestSale->sold_at)->timezone('Asia/Kathmandu')->format('h:i A') : 'Midday';
            $majorEvents[] = [
                'type' => 'highest_sale',
                'time' => $highestTime,
                'title' => 'Highest Value Sale of Shift',
                'badge' => 'TOP SALE',
                'detail' => 'Ticket #' . $highestSale->sale_number . ' for Rs. ' . number_format($highestSale->total_amount, 2) . ' (' . $highestSale->items->sum('quantity') . ' items)',
                'subdetail' => 'Customer: ' . ($highestSale->customer_name ?: 'Walk-in') . ' · Staff: ' . ($highestSale->staff_name ?: 'Cashier') . ' · Tender: ' . ($methodConfigs[$highestSale->payment_method]['label'] ?? $highestSale->payment_method),
            ];
        }

        // Promotional Discounts Conceded
        if (!empty($discountedSalesList)) {
            $majorEvents[] = [
                'type' => 'discounts',
                'time' => 'All Day',
                'title' => 'Discounts & Concessions Conceded',
                'badge' => 'PROMO',
                'detail' => count($discountedSalesList) . ' orders granted promotional or courtesy discounts totaling Rs. ' . number_format($discountTotal, 2),
                'subdetail' => 'Average discount: Rs. ' . number_format($avgDiscount, 2) . ' per ticket (' . $discountRatePct . '% discount rate)',
            ];
        }

        // Split Payment Tenders
        if ($splitCount > 0) {
            $majorEvents[] = [
                'type' => 'split_payments',
                'time' => 'Multiple',
                'title' => 'Split Payment Tenders Processed',
                'badge' => 'SPLIT',
                'detail' => $splitCount . ' dual-tender checkouts handled (Rs. ' . number_format($splitCashRevenue, 2) . ' cash + Rs. ' . number_format($splitDigitalRevenue, 2) . ' digital)',
                'subdetail' => 'Reconciled cleanly between cash till and digital wallet settlements',
            ];
        }

        // Voided & Cancelled Transactions (with full audit)
        if ($voidedCount > 0) {
            $voidedSalesList = $voidedQuery->get();
            foreach ($voidedSalesList as $vs) {
                $vTime = $vs->voided_at ? Carbon::parse($vs->voided_at)->timezone('Asia/Kathmandu')->format('h:i A') : ($vs->sold_at ? Carbon::parse($vs->sold_at)->timezone('Asia/Kathmandu')->format('h:i A') : 'During Shift');
                $staffWhoVoided = $vs->voided_by;
                if (is_numeric($staffWhoVoided)) {
                    $staffUser = \App\Models\User::find($staffWhoVoided);
                    $staffWhoVoided = $staffUser ? $staffUser->name : ('Staff #' . $staffWhoVoided);
                }
                $majorEvents[] = [
                    'type' => 'voided',
                    'time' => $vTime,
                    'title' => 'Voided / Cancelled Sale #' . $vs->sale_number,
                    'badge' => 'VOID',
                    'detail' => 'Ticket #' . $vs->sale_number . ' (Rs. ' . number_format($vs->total_amount, 2) . ') VOIDED by ' . ($staffWhoVoided ?: ($vs->staff_name ?: 'Cashier')),
                    'subdetail' => 'Void Reason: ' . ($vs->void_reason ?: 'Customer cancellation / return'),
                ];
            }
        } else {
            $majorEvents[] = [
                'type' => 'void_clean',
                'time' => 'All Day',
                'title' => 'Zero Sales Voided (100% Clean Shift)',
                'badge' => 'AUDIT',
                'detail' => 'No orders were voided or reversed during this operating period',
                'subdetail' => 'All completed register transactions verified intact',
            ];
        }

        // Shift / Day Close (Latest Sale Punch)
        $lastSale = $periodSales->sortBy('sold_at')->last();
        if ($lastSale && $count > 1) {
            $lastTime = $lastSale->sold_at ? Carbon::parse($lastSale->sold_at)->timezone('Asia/Kathmandu')->format('h:i A') : 'Evening';
            $majorEvents[] = [
                'type' => 'shift_close',
                'time' => $lastTime,
                'title' => 'Latest Sale Punch / Register Active',
                'badge' => 'ACTIVE',
                'detail' => 'Final recorded ticket #' . $lastSale->sale_number . ' punched by ' . ($lastSale->staff_name ?: 'Cashier') . ' (Rs. ' . number_format($lastSale->total_amount, 2) . ')',
                'subdetail' => 'Customer: ' . ($lastSale->customer_name ?: 'Walk-in') . ' · Total ' . $count . ' transactions completed',
            ];
        }

        // 15. Legacy fallbacks for backward-compatibility with tests
        $paymentsLegacy = [
            'cash' => ['label' => 'Cash', 'count' => 0, 'amount' => 0.0],
            'card' => ['label' => 'Card Terminal (POS)', 'count' => 0, 'amount' => 0.0],
            'esewa' => ['label' => 'eSewa / Digital Wallet', 'count' => 0, 'amount' => 0.0],
            'khalti' => ['label' => 'Khalti / Fonepay QR', 'count' => 0, 'amount' => 0.0],
            'bank_transfer' => ['label' => 'Bank Wire / ConnectIPS', 'count' => 0, 'amount' => 0.0],
            'other' => ['label' => 'Other', 'count' => 0, 'amount' => 0.0],
        ];
        foreach ($allActive as $s) {
            $m = $s->payment_method;
            if (!isset($paymentsLegacy[$m])) {
                $paymentsLegacy[$m] = ['label' => ucfirst(str_replace('_', ' ', (string)$m)), 'count' => 0, 'amount' => 0.0];
            }
            $paymentsLegacy[$m]['count']++;
            $paymentsLegacy[$m]['amount'] += (float)$s->total_amount;
        }

        $channelsLegacy = [
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
            if (!isset($channelsLegacy[$c])) {
                $channelsLegacy[$c] = ['label' => ucfirst(str_replace('_', ' ', (string)$c)), 'count' => 0, 'amount' => 0.0];
            }
            $channelsLegacy[$c]['count']++;
            $channelsLegacy[$c]['amount'] += (float)$s->total_amount;
        }

        return [
            // Backward compatibility
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
            'payments' => $paymentsLegacy,
            'channels' => $channelsLegacy,
            'total_lifetime_count' => $allActive->count(),
            'total_lifetime_revenue' => (float)$allActive->sum('total_amount'),
            'total_lifetime_profit_npr' => (float)$allActive->sum('total_profit_npr'),

            // Upgraded Real-Time Analytics
            'period_label' => $periodLabel,
            'period' => $period,
            'period_kpis' => [
                'revenue' => $revenue,
                'revenue_growth_pct' => $revenueGrowthPct,
                'orders_count' => $count,
                'orders_growth_pct' => $countGrowthPct,
                'units_sold' => $unitsSold,
                'aov' => $aov,
                'upt' => $upt,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'discount_rate_pct' => $discountRatePct,
                'avg_discount_per_order' => $avgDiscount,
                'profit_npr' => $profitTotal,
                'margin_pct' => $marginPct,
                'voided_count' => $voidedCount,
                'voided_amount' => $voidedAmount,
            ],
            'payment_analytics' => [
                'cash_vs_digital' => [
                    'physical_cash_total' => $physicalCashTotal,
                    'physical_cash_pct' => $physicalCashPct,
                    'physical_cash_count' => $cashTransCount,
                    'digital_total' => $digitalTotal,
                    'digital_pct' => $digitalPct,
                    'digital_count' => $pureDigitalCount + $splitCount,
                ],
                'drawer_reconciliation' => [
                    'opening_balance' => $openingBalance,
                    'net_cash_collected' => $physicalCashTotal,
                    'cash_tendered' => $cashTendered,
                    'change_returned' => $changeReturned,
                    'net_cash_in_drawer' => $netCashInDrawer,
                    'cash_transactions' => $cashTransCount,
                    'avg_cash_ticket' => $avgCashTicket,
                ],
                'methods_breakdown' => $methodsBreakdown,
            ],
            'hourly_distribution' => $hourly,
            'peak_traffic' => [
                'peak_hour' => $peakHour,
                'peak_revenue' => $peakRev,
                'peak_orders' => $peakOrders,
            ],
            'staff_performance' => $staffPerformance,
            'channel_analytics' => $channelAnalytics,
            'customer_insights' => $customerInsights,
            'top_products' => $topProducts,
            'recent_transactions' => $recentTransactions,
            'all_sales' => $allSalesList,
            'discounted_sales' => $discountedSalesList,
            'major_events' => $majorEvents,
            'session_details' => $sessionDetails,
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
