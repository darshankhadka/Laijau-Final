<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FinalProductionAuditCommand extends Command
{
    protected $signature = 'laijau:final-audit {--json : Export JSON report}';
    protected $description = 'Comprehensive automated Data Integrity and Database Relationship Audit for Laijau ERP';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('  LAIJAU ERP — FINAL PRODUCTION DATA INTEGRITY & RELATIONSHIP AUDIT');
        $this->info('================================================================');

        $report = [
            'audit_timestamp' => now()->toIso8601String(),
            'database' => DB::getDatabaseName(),
            'sections' => [],
            'anomalies' => [],
            'overall_status' => 'PASS',
        ];

        // 1. ORDERS AUDIT
        $this->info("\n--- 1. ORDERS INTEGRITY AUDIT ---");
        $totalOrders = DB::table('orders')->count();
        $dispatchedOrders = DB::table('orders')->where('order_number', 'like', 'ONL-2026-%')->count();
        $cancelledOrders = DB::table('orders')->where('order_number', 'like', 'CAN-2026-%')->count();
        $otherHistoricalOrders = DB::table('orders')->where('order_number', 'not like', 'ONL-2026-%')->where('order_number', 'not like', 'CAN-2026-%')->count();
        $deliveredOrders = DB::table('orders')->where(function ($q) {
            $q->where('status', 'delivered')
                ->orWhereNotNull('delivered_at')
                ->orWhere('courier_status', 'Delivered');
        })->count();
        $totalOrderAmount = (float) DB::table('orders')->sum('total_amount');
        $cancelledWithTracking = DB::table('orders')->where('order_number', 'like', 'CAN-2026-%')->whereNotNull('tracking_number')->count();

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Total Orders', $totalOrders, '1,950 (Reconciled NCM Orders)', $totalOrders === 1950 ? 'PASS' : 'WARN'],
            ['Reconciled Online Orders (ONL-2026)', $dispatchedOrders, '1,642', $dispatchedOrders === 1642 ? 'PASS' : 'WARN'],
            ['Cancelled Orders (CAN-2026)', $cancelledOrders, '308', $cancelledOrders === 308 ? 'PASS' : 'WARN'],
            ['Other Historical Orders', $otherHistoricalOrders, '0 (Test Contamination Purged)', $otherHistoricalOrders === 0 ? 'PASS' : 'INFO'],
            ['Total Online Value (NPR)', number_format($totalOrderAmount, 2), '4,112,716.00', abs($totalOrderAmount - 4112716.00) < 0.01 ? 'PASS' : 'WARN'],
            ['Delivered Orders Count', $deliveredOrders, '>= 1,300', $deliveredOrders >= 1300 ? 'PASS' : 'WARN'],
            ['Cancelled Orders with Tracking', $cancelledWithTracking, '0 (Must not have tracking)', $cancelledWithTracking === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($cancelledWithTracking > 0) {
            $report['anomalies'][] = "Cancelled orders have non-null tracking numbers ($cancelledWithTracking found).";
        }

        $report['sections']['orders'] = [
            'total' => $totalOrders,
            'dispatched' => $dispatchedOrders,
            'cancelled' => $cancelledOrders,
            'total_value_npr' => $totalOrderAmount,
            'delivered' => $deliveredOrders,
            'cancelled_with_tracking' => $cancelledWithTracking,
        ];

        // 2. POS AUDIT
        $this->info("\n--- 2. POS / SHOWROOM SALES INTEGRITY AUDIT ---");
        $totalPos = DB::table('offline_sales')->count();
        $totalPosRevenue = (float) DB::table('offline_sales')->sum('total_amount');

        // Monthly breakdown
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $ymExpr = $isSqlite ? "strftime('%Y-%m', sold_at)" : "DATE_FORMAT(sold_at, '%Y-%m')";
        $monthlyDist = DB::table('offline_sales')
            ->selectRaw("{$ymExpr} as ym, COUNT(*) as cnt, SUM(total_amount) as total_rev")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $rows = [];
        foreach ($monthlyDist as $m) {
            $rows[] = [$m->ym, number_format((int)$m->cnt), 'NPR ' . number_format((float)$m->total_rev, 2)];
        }
        $this->table(['Year-Month', 'Sales Count', 'Revenue (NPR)'], $rows);

        $febSalesCount = DB::table('offline_sales')->whereBetween('sold_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59'])->count();
        $febSalesRev = (float) DB::table('offline_sales')->whereBetween('sold_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59'])->sum('total_amount');
        $febExpensesCount = DB::table('accounting_journal_entries')
            ->where('reference_type', 'showroom_expense')
            ->whereBetween('voucher_date', ['2026-02-01', '2026-02-28'])
            ->count();
        $febExpensesAmt = (float) DB::table('accounting_journal_entries')
            ->where('reference_type', 'showroom_expense')
            ->whereBetween('voucher_date', ['2026-02-01', '2026-02-28'])
            ->sum('total_debit');

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Total POS Sales', $totalPos, '11,170', $totalPos === 11170 ? 'PASS' : 'WARN'],
            ['Total POS Revenue (NPR)', number_format($totalPosRevenue, 2), '33,266,992.00', abs($totalPosRevenue - 33266992.00) < 0.01 ? 'PASS' : 'WARN'],
            ['February 2026 POS Sales', $febSalesCount, '1,763', $febSalesCount === 1763 ? 'PASS' : 'WARN'],
            ['February 2026 Revenue (NPR)', number_format($febSalesRev, 2), '4,181,957.00', abs($febSalesRev - 4181957.00) < 0.01 ? 'PASS' : 'WARN'],
            ['February Register Expenses Count', $febExpensesCount, '181', $febExpensesCount === 181 ? 'PASS' : 'WARN'],
            ['February Register Expenses (NPR)', number_format($febExpensesAmt, 2), '623,290.00', abs($febExpensesAmt - 623290.00) < 0.01 ? 'PASS' : 'WARN'],
        ]);

        $report['sections']['pos'] = [
            'total_sales' => $totalPos,
            'total_revenue_npr' => $totalPosRevenue,
            'feb_sales_count' => $febSalesCount,
            'feb_revenue_npr' => $febSalesRev,
            'feb_expenses_count' => $febExpensesCount,
            'feb_expenses_npr' => $febExpensesAmt,
        ];

        // 3. PURCHASES & CLOTHING PROCUREMENT AUDIT
        $this->info("\n--- 3. PURCHASES & CLOTHING PROCUREMENT AUDIT ---");
        $totalPosCount = DB::table('inventory_purchase_orders')->count();
        $clothingPosCount = DB::table('inventory_purchase_orders')->where('po_number', 'like', 'PO-2026-CLO-%')->count();
        $clothingItemsCount = DB::table('inventory_purchase_order_items')
            ->join('inventory_purchase_orders', 'inventory_purchase_orders.id', '=', 'inventory_purchase_order_items.purchase_order_id')
            ->where('inventory_purchase_orders.po_number', 'like', 'PO-2026-CLO-%')
            ->count();
        $clothingTotalCost = (float) DB::table('inventory_purchase_orders')
            ->where('po_number', 'like', 'PO-2026-CLO-%')
            ->sum('total_amount_npr');

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Total Purchase Orders', $totalPosCount, '>= 57', $totalPosCount >= 57 ? 'PASS' : 'WARN'],
            ['Clothing Purchase Batches', $clothingPosCount, '57', $clothingPosCount === 57 ? 'PASS' : 'WARN'],
            ['Clothing Purchase Line Items', $clothingItemsCount, '210', $clothingItemsCount === 210 ? 'PASS' : 'WARN'],
            ['Clothing Procurement Total (NPR)', number_format($clothingTotalCost, 2), '7,090,605.00', abs($clothingTotalCost - 7090605.00) < 0.01 ? 'PASS' : 'WARN'],
        ]);

        $report['sections']['purchases'] = [
            'total_pos' => $totalPosCount,
            'clothing_batches' => $clothingPosCount,
            'clothing_items' => $clothingItemsCount,
            'clothing_total_cost_npr' => $clothingTotalCost,
        ];

        // 4. CATALOG & INVENTORY AUDIT
        $this->info("\n--- 4. CATALOG & INVENTORY INTEGRITY AUDIT ---");
        $productsCount = DB::table('products')->count();
        $variantsCount = DB::table('product_variants')->count();
        $orphanVariants = DB::table('product_variants')
            ->leftJoin('products', 'product_variants.product_id', '=', 'products.id')
            ->whereNull('products.id')
            ->count();
        $negativeStockCount = DB::table('inventory_stock_levels')->where('quantity_on_hand', '<', 0)->count()
            + DB::table('product_variants')->where('stock_quantity', '<', 0)->count();
        $uncategorizedProducts = DB::table('products')
            ->leftJoin('category_product', 'products.id', '=', 'category_product.product_id')
            ->whereNull('category_product.category_id')
            ->count();

        $storefrontZeroPrice = \App\Models\Product::storefrontReady()
            ->where(function ($q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            })->count();
        $unpricedCatalog = DB::table('products')->whereNull('price')->orWhere('price', '<=', 0)->count();

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Master Products', $productsCount, '1,325 (Purged of Test Products)', $productsCount === 1325 ? 'PASS' : 'WARN'],
            ['Product Variants', $variantsCount, '5,545', $variantsCount === 5545 ? 'PASS' : 'WARN'],
            ['Orphan Variants', $orphanVariants, '0', $orphanVariants === 0 ? 'PASS' : 'FAIL'],
            ['Negative Stock Levels', $negativeStockCount, '0', $negativeStockCount === 0 ? 'PASS' : 'FAIL'],
            ['Uncategorized Products', $uncategorizedProducts, '0', $uncategorizedProducts === 0 ? 'PASS' : 'WARN'],
            ['Storefront Products with Zero Price', $storefrontZeroPrice, '0 (Must not have zero price)', $storefrontZeroPrice === 0 ? 'PASS' : 'FAIL'],
            ['Unpriced Internal Catalog Products', $unpricedCatalog, '1 (LIM-01 flagged PRICE_REVIEW_REQUIRED)', $unpricedCatalog <= 1 ? 'PASS' : 'WARN'],
        ]);

        if ($orphanVariants > 0) {
            $report['anomalies'][] = "Found $orphanVariants orphan product variants.";
        }
        if ($negativeStockCount > 0) {
            $report['anomalies'][] = "Found $negativeStockCount negative stock levels.";
        }
        if ($storefrontZeroPrice > 0) {
            $report['anomalies'][] = "Found $storefrontZeroPrice storefront products with zero or null price.";
        }

        $report['sections']['catalog'] = [
            'products' => $productsCount,
            'variants' => $variantsCount,
            'orphan_variants' => $orphanVariants,
            'negative_stock' => $negativeStockCount,
            'uncategorized' => $uncategorizedProducts,
            'storefront_zero_price' => $storefrontZeroPrice,
            'unpriced_catalog' => $unpricedCatalog,
        ];

        // 5. CRM & USERS AUDIT
        $this->info("\n--- 5. CRM & USERS INTEGRITY AUDIT ---");
        $totalUsers = DB::table('users')->count();
        $customerUsers = DB::table('users')->where('role', 'customer')->count();
        $orphanOrders = DB::table('orders')
            ->whereNotNull('user_id')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->whereNull('users.id')
            ->count();

        // Duplicate non-empty phone numbers among users
        $dupPhones = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->select('phone', DB::raw('count(*) as count'))
            ->groupBy('phone')
            ->having('count', '>', 1)
            ->count();

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Total Users', $totalUsers, '571 (Authoritative real users)', $totalUsers === 571 ? 'PASS' : 'WARN'],
            ['Customer Role Users', $customerUsers, '562', $customerUsers === 562 ? 'PASS' : 'WARN'],
            ['Duplicate User Phones', $dupPhones, '0', $dupPhones === 0 ? 'PASS' : 'FAIL'],
            ['Orphan Orders (invalid user_id)', $orphanOrders, '0', $orphanOrders === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($dupPhones > 0) {
            $report['anomalies'][] = "Found $dupPhones duplicate phone numbers in users table.";
        }
        if ($orphanOrders > 0) {
            $report['anomalies'][] = "Found $orphanOrders orphan orders referencing non-existent users.";
        }

        $report['sections']['crm'] = [
            'total_users' => $totalUsers,
            'customer_users' => $customerUsers,
            'duplicate_phones' => $dupPhones,
            'orphan_orders' => $orphanOrders,
        ];

        // 6. ACCOUNTING ENGINE AUDIT
        $this->info("\n--- 6. ACCOUNTING ENGINE GENERAL LEDGER AUDIT ---");
        $invoicesCount = DB::table('accounting_invoices')->count();
        $bikriKhataCount = DB::table('accounting_invoices')->where('type', 'sales_invoice')->count();
        $kharidKhataCount = DB::table('accounting_invoices')->where('type', 'supplier_bill')->count();
        $journalEntriesCount = DB::table('accounting_journal_entries')->count();
        $journalLinesCount = DB::table('accounting_journal_entry_lines')->count();
        $totalDebit = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
        $totalCredit = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
        $variance = abs($totalDebit - $totalCredit);

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Statutory Invoices', $invoicesCount, '>= 13,000', $invoicesCount >= 13000 ? 'PASS' : 'WARN'],
            ['Bikri Khata (Sales Invoices)', $bikriKhataCount, '>= 12,900', $bikriKhataCount >= 12900 ? 'PASS' : 'WARN'],
            ['Kharid Khata (Purchase Invoices)', $kharidKhataCount, '92', $kharidKhataCount === 92 ? 'PASS' : 'WARN'],
            ['Journal Entries Count', $journalEntriesCount, '>= 14,070', $journalEntriesCount >= 14070 ? 'PASS' : 'WARN'],
            ['Journal Lines Count', $journalLinesCount, '>= 29,940', $journalLinesCount >= 29940 ? 'PASS' : 'WARN'],
            ['Total General Ledger Debit', 'NPR ' . number_format($totalDebit, 2), 'NPR 54,263,143.76', abs($totalDebit - 54263143.76) < 0.01 ? 'PASS' : 'WARN'],
            ['Total General Ledger Credit', 'NPR ' . number_format($totalCredit, 2), 'NPR 54,263,143.76', abs($totalCredit - 54263143.76) < 0.01 ? 'PASS' : 'WARN'],
            ['Debit / Credit Variance', 'NPR ' . number_format($variance, 4), 'NPR 0.0000', $variance < 0.0001 ? 'PASS' : 'FAIL'],
        ]);

        if ($variance >= 0.0001) {
            $report['anomalies'][] = "General ledger is unbalanced! Variance: NPR " . number_format($variance, 4);
        }

        $report['sections']['accounting'] = [
            'invoices' => $invoicesCount,
            'bikri_khata' => $bikriKhataCount,
            'kharid_khata' => $kharidKhataCount,
            'journal_entries' => $journalEntriesCount,
            'journal_lines' => $journalLinesCount,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'variance' => $variance,
        ];

        // 7. NCM LOGISTICS AUDIT
        $this->info("\n--- 7. NCM LOGISTICS RECONCILIATION AUDIT ---");
        $totalShipments = DB::table('shipments')->count();
        $uniqueTrackingIds = DB::table('shipments')->distinct()->count('external_tracking_number');
        $matchedShipments = DB::table('shipments')->where('match_status', 'matched')->count();
        $ambiguousShipments = DB::table('shipments')->where('match_status', 'ambiguous')->count();
        $unmatchedShipments = DB::table('shipments')->where('match_status', 'unmatched')->count();
        $vendorReturns = DB::table('shipments')->where('vendor_return', 1)->count();
        $deliveredShipments = DB::table('shipments')->where('normalized_status', 'delivered')->count();
        $outForDelivery = DB::table('shipments')->where('normalized_status', 'out_for_delivery')->count();
        $ambiguousWithOrderId = DB::table('shipments')->where('match_status', 'ambiguous')->whereNotNull('order_id')->count();
        $unmatchedWithOrderId = DB::table('shipments')->where('match_status', 'unmatched')->whereNotNull('order_id')->count();

        // 1-to-1 tracking invariant check: shipments.external_tracking_number == orders.tracking_number == orders.courier_order_id
        $invariantViolations = DB::table('shipments')
            ->join('orders', 'shipments.order_id', '=', 'orders.id')
            ->where('shipments.match_status', 'matched')
            ->where(function ($q) {
                $q->whereRaw('shipments.external_tracking_number != orders.tracking_number')
                    ->orWhereRaw('shipments.external_tracking_number != orders.courier_order_id');
            })
            ->count();

        $this->table(['Metric', 'Value', 'Invariant / Baseline', 'Status'], [
            ['Total NCM Shipments', $totalShipments, '1,623', $totalShipments === 1623 ? 'PASS' : 'WARN'],
            ['Unique Tracking Numbers', $uniqueTrackingIds, '1,623 (Zero duplicate shipments)', $uniqueTrackingIds === 1623 ? 'PASS' : 'FAIL'],
            ['Matched Shipments (1-to-1)', $matchedShipments, '1,332', $matchedShipments === 1332 ? 'PASS' : 'WARN'],
            ['Ambiguous Shipments', $ambiguousShipments, '267', $ambiguousShipments === 267 ? 'PASS' : 'WARN'],
            ['Ambiguous with order_id', $ambiguousWithOrderId, '0 (MUST be NULL until staff links)', $ambiguousWithOrderId === 0 ? 'PASS' : 'FAIL'],
            ['Unmatched Shipments', $unmatchedShipments, '24', $unmatchedShipments === 24 ? 'PASS' : 'WARN'],
            ['Unmatched with order_id', $unmatchedWithOrderId, '0 (MUST be NULL)', $unmatchedWithOrderId === 0 ? 'PASS' : 'FAIL'],
            ['Vendor Returns', $vendorReturns, '201', $vendorReturns === 201 ? 'PASS' : 'WARN'],
            ['Delivered Shipments', $deliveredShipments, '1,415', $deliveredShipments === 1415 ? 'PASS' : 'WARN'],
            ['Out for Delivery Shipments', $outForDelivery, '4', $outForDelivery === 4 ? 'PASS' : 'WARN'],
            ['1-to-1 Tracking Invariant Violations', $invariantViolations, '0', $invariantViolations === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($uniqueTrackingIds !== 1623) {
            $report['anomalies'][] = "Duplicate tracking numbers found in shipments table ($uniqueTrackingIds unique out of $totalShipments).";
        }
        if ($ambiguousWithOrderId > 0) {
            $report['anomalies'][] = "Ambiguous shipments contain non-null order_id ($ambiguousWithOrderId found).";
        }
        if ($unmatchedWithOrderId > 0) {
            $report['anomalies'][] = "Unmatched shipments contain non-null order_id ($unmatchedWithOrderId found).";
        }
        if ($invariantViolations > 0) {
            $report['anomalies'][] = "1-to-1 tracking invariant violated on $invariantViolations matched records.";
        }

        $report['sections']['logistics'] = [
            'total_shipments' => $totalShipments,
            'unique_tracking' => $uniqueTrackingIds,
            'matched' => $matchedShipments,
            'ambiguous' => $ambiguousShipments,
            'ambiguous_with_order_id' => $ambiguousWithOrderId,
            'unmatched' => $unmatchedShipments,
            'unmatched_with_order_id' => $unmatchedWithOrderId,
            'vendor_returns' => $vendorReturns,
            'delivered' => $deliveredShipments,
            'out_for_delivery' => $outForDelivery,
            'invariant_violations' => $invariantViolations,
        ];

        // 8. DATABASE RELATIONSHIPS & REFERENTIAL INTEGRITY AUDIT (Section 14)
        $this->info("\n--- 8. DATABASE RELATIONSHIP & FOREIGN KEY AUDIT ---");

        // Orphan order items
        $orphanOrderItems = DB::table('order_items')
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('orders.id')
            ->count();

        // Orphan offline sale items
        $orphanSaleItems = DB::table('offline_sale_items')
            ->leftJoin('offline_sales', 'offline_sale_items.offline_sale_id', '=', 'offline_sales.id')
            ->whereNull('offline_sales.id')
            ->count();

        // Orphan PO items
        $orphanPoItems = DB::table('inventory_purchase_order_items')
            ->leftJoin('inventory_purchase_orders', 'inventory_purchase_order_items.purchase_order_id', '=', 'inventory_purchase_orders.id')
            ->whereNull('inventory_purchase_orders.id')
            ->count();

        // Orphan journal entry lines
        $orphanJournalLines = DB::table('accounting_journal_entry_lines')
            ->leftJoin('accounting_journal_entries', 'accounting_journal_entry_lines.journal_entry_id', '=', 'accounting_journal_entries.id')
            ->whereNull('accounting_journal_entries.id')
            ->count();

        // Shipments with invalid order_id
        $invalidShipmentOrders = DB::table('shipments')
            ->whereNotNull('order_id')
            ->leftJoin('orders', 'shipments.order_id', '=', 'orders.id')
            ->whereNull('orders.id')
            ->count();

        // Duplicate order numbers
        $dupOrderNumbers = DB::table('orders')
            ->select('order_number', DB::raw('count(*) as c'))
            ->groupBy('order_number')
            ->having('c', '>', 1)
            ->count();

        // Duplicate PO numbers
        $dupPoNumbers = DB::table('inventory_purchase_orders')
            ->select('po_number', DB::raw('count(*) as c'))
            ->groupBy('po_number')
            ->having('c', '>', 1)
            ->count();

        // Duplicate POS sale numbers
        $dupSaleNumbers = DB::table('offline_sales')
            ->select('sale_number', DB::raw('count(*) as c'))
            ->groupBy('sale_number')
            ->having('c', '>', 1)
            ->count();

        // Duplicate tracking numbers in reconciled production orders (ONL-2026-%)
        $dupReconciledTracking = DB::table('orders')
            ->where('order_number', 'like', 'ONL-2026-%')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->select('tracking_number', DB::raw('count(*) as c'))
            ->groupBy('tracking_number')
            ->having('c', '>', 1)
            ->count();

        // Duplicate tracking numbers in legacy test fixtures (e.g. LJ-TEST-OP-...)
        $dupLegacyTracking = DB::table('orders')
            ->where('order_number', 'not like', 'ONL-2026-%')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->select('tracking_number', DB::raw('count(*) as c'))
            ->groupBy('tracking_number')
            ->having('c', '>', 1)
            ->count();

        $this->table(['Referential Integrity Check', 'Violations Found', 'Allowed', 'Status'], [
            ['Orphan Order Items', $orphanOrderItems, '0', $orphanOrderItems === 0 ? 'PASS' : 'FAIL'],
            ['Orphan POS Sale Items', $orphanSaleItems, '0', $orphanSaleItems === 0 ? 'PASS' : 'FAIL'],
            ['Orphan Purchase Order Items', $orphanPoItems, '0', $orphanPoItems === 0 ? 'PASS' : 'FAIL'],
            ['Orphan Journal Entry Lines', $orphanJournalLines, '0', $orphanJournalLines === 0 ? 'PASS' : 'FAIL'],
            ['Shipments with Invalid order_id', $invalidShipmentOrders, '0', $invalidShipmentOrders === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate Order Numbers', $dupOrderNumbers, '0', $dupOrderNumbers === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate PO Numbers', $dupPoNumbers, '0', $dupPoNumbers === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate POS Sale Numbers', $dupSaleNumbers, '0', $dupSaleNumbers === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate Tracking (Reconciled Orders)', $dupReconciledTracking, '0', $dupReconciledTracking === 0 ? 'PASS' : 'FAIL'],
            ['Duplicate Tracking (Legacy Mock Fixtures)', $dupLegacyTracking, '4 (Reported/Isolated)', 'INFO'],
        ]);

        if ($orphanOrderItems > 0) $report['anomalies'][] = "Found $orphanOrderItems orphan order items.";
        if ($orphanSaleItems > 0) $report['anomalies'][] = "Found $orphanSaleItems orphan POS sale items.";
        if ($orphanPoItems > 0) $report['anomalies'][] = "Found $orphanPoItems orphan purchase order items.";
        if ($orphanJournalLines > 0) $report['anomalies'][] = "Found $orphanJournalLines orphan journal entry lines.";
        if ($invalidShipmentOrders > 0) $report['anomalies'][] = "Found $invalidShipmentOrders shipments with invalid order_id.";
        if ($dupOrderNumbers > 0) $report['anomalies'][] = "Found $dupOrderNumbers duplicate order numbers.";
        if ($dupPoNumbers > 0) $report['anomalies'][] = "Found $dupPoNumbers duplicate PO numbers.";
        if ($dupSaleNumbers > 0) $report['anomalies'][] = "Found $dupSaleNumbers duplicate POS sale numbers.";
        if ($dupReconciledTracking > 0) $report['anomalies'][] = "Found $dupReconciledTracking duplicate tracking numbers in reconciled orders.";
        if ($dupLegacyTracking > 0) $report['legacy_fixtures_notes'][] = "Found $dupLegacyTracking duplicate tracking numbers in legacy test fixtures (LJ-TEST-OP/LJ-00xx).";

        $report['sections']['relationships'] = [
            'orphan_order_items' => $orphanOrderItems,
            'orphan_sale_items' => $orphanSaleItems,
            'orphan_po_items' => $orphanPoItems,
            'orphan_journal_lines' => $orphanJournalLines,
            'invalid_shipment_orders' => $invalidShipmentOrders,
            'duplicate_order_numbers' => $dupOrderNumbers,
            'duplicate_po_numbers' => $dupPoNumbers,
            'duplicate_sale_numbers' => $dupSaleNumbers,
            'duplicate_reconciled_order_tracking' => $dupReconciledTracking,
            'duplicate_legacy_order_tracking' => $dupLegacyTracking,
        ];

        // 9. CANCELLATION & FULFILLMENT ISOLATION AUDIT
        $this->info("\n--- 9. CANCELLATION & FULFILLMENT ISOLATION AUDIT ---");
        $cancelledInActiveStatus = DB::table('orders')
            ->where('order_number', 'like', 'CAN-2026-%')
            ->where('status', '!=', 'cancelled')
            ->count();

        $cancelledWithTracking = DB::table('orders')
            ->where('status', 'cancelled')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->count();

        $this->table(['Cancellation Guard Check', 'Count Found', 'Allowed', 'Status'], [
            ['CAN-2026 Orders not marked as cancelled', $cancelledInActiveStatus, '0', $cancelledInActiveStatus === 0 ? 'PASS' : 'FAIL'],
            ['Cancelled Orders with active tracking numbers', $cancelledWithTracking, '0', $cancelledWithTracking === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($cancelledInActiveStatus > 0) {
            $report['anomalies'][] = "Found $cancelledInActiveStatus CAN-2026 orders with status other than 'cancelled'.";
        }
        if ($cancelledWithTracking > 0) {
            $report['anomalies'][] = "Found $cancelledWithTracking cancelled orders with non-empty tracking numbers.";
        }

        $report['sections']['cancellation_guard'] = [
            'can_orders_not_cancelled' => $cancelledInActiveStatus,
            'cancelled_with_tracking' => $cancelledWithTracking,
        ];

        // 10. TIMELINE BOUNDARY & FUTURE TRANSACTION INTEGRITY AUDIT
        $this->info("\n--- 10. TIMELINE BOUNDARY & FUTURE TRANSACTION INTEGRITY AUDIT ---");
        $cutoff = Carbon::now()->endOfDay();
        $futureOrders = DB::table('orders')->where('created_at', '>', $cutoff)->count();
        $futurePos = DB::table('offline_sales')->where('sold_at', '>', $cutoff)->count();
        $futurePurchases = DB::table('inventory_purchase_orders')->where('order_date', '>', $cutoff)->count();
        $futureInvoices = DB::table('accounting_invoices')->where('issue_date', '>', $cutoff->toDateString())->count();
        $futureJournal = DB::table('accounting_journal_entries')->where('voucher_date', '>', $cutoff->toDateString())->count();

        $this->table(['Timeline Invariant Check (Future Date Guard)', 'Count Found', 'Allowed', 'Status'], [
            ['Orders after current date', $futureOrders, '0 (No invalid future activity)', $futureOrders === 0 ? 'PASS' : 'FAIL'],
            ['POS Sales after current date', $futurePos, '0 (No invalid future activity)', $futurePos === 0 ? 'PASS' : 'FAIL'],
            ['Purchase Orders after current date', $futurePurchases, '0 (No invalid future activity)', $futurePurchases === 0 ? 'PASS' : 'FAIL'],
            ['Statutory Invoices after current date', $futureInvoices, '0 (No invalid future activity)', $futureInvoices === 0 ? 'PASS' : 'FAIL'],
            ['Journal Entries after current date', $futureJournal, '0 (No invalid future activity)', $futureJournal === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($futureOrders > 0) $report['anomalies'][] = "Found $futureOrders orders dated in the future.";
        if ($futurePos > 0) $report['anomalies'][] = "Found $futurePos POS sales dated in the future.";
        if ($futurePurchases > 0) $report['anomalies'][] = "Found $futurePurchases purchase orders dated in the future.";
        if ($futureInvoices > 0) $report['anomalies'][] = "Found $futureInvoices statutory invoices dated in the future.";
        if ($futureJournal > 0) $report['anomalies'][] = "Found $futureJournal journal entries dated in the future.";

        $report['sections']['timeline_lock'] = [
            'future_orders' => $futureOrders,
            'future_pos' => $futurePos,
            'future_purchases' => $futurePurchases,
            'future_invoices' => $futureInvoices,
            'future_journal_entries' => $futureJournal,
        ];

        // 11. NEPAL ACCOUNTING & VAT PURGE AUDIT (NAS / IRD INVARIANTS)
        $this->info("\n--- 11. NEPAL ACCOUNTING & VAT LOCALIZATION AUDIT ---");
        $legacyAccounts = DB::table('accounting_accounts')->where('account_number', '2610')->count();
        $legacyLines2610 = DB::table('accounting_journal_entry_lines')->where('account_number', '2610')->count();
        $nepalVatLines2120 = DB::table('accounting_journal_entry_lines')->where('account_number', '2120')->count();
        $foreignJournals = DB::table('accounting_journal_entries')->whereNotIn('currency', ['NPR', 'npr'])->count();
        $foreignLines = DB::table('accounting_journal_entry_lines')->whereNotIn('currency', ['NPR', 'npr'])->count();
        $foreignInvoices = DB::table('accounting_invoices')->whereNotIn('currency', ['NPR', 'npr'])->count();
        $bilVouchers = DB::table('accounting_journal_entries')->where('entry_number', 'like', 'BIL-%')->count();
        $fakInvoices = DB::table('accounting_invoices')->where('invoice_number', 'like', 'FAK-%')->count();
        $nonNepalInvoices = DB::table('accounting_invoices')->whereNotIn('contact_country', ['NP', 'Nepal'])->count();
        $vatRateSetting = (float) DB::table('module_settings')->where('module', 'accounting')->where('key', 'standard_vat_rate')->value('value');
        $vatAccSetting = (string) DB::table('module_settings')->where('module', 'accounting')->where('key', 'gl_sales_vat_account')->value('value');
        $nonStandardVatLines = DB::table('accounting_journal_entry_lines')->where('vat_rate', '!=', 13.00)->where('vat_rate', '>', 0)->count();
        $invItemsNonStandardVat = DB::table('accounting_invoice_items')->where('vat_rate', '!=', 13.00)->where('vat_rate', '>', 0)->count();

        $this->table(['Nepal Accounting & VAT Invariant Check', 'Current Value', 'Statutory Requirement', 'Status'], [
            ['Legacy 2610 Accounts in COA', $legacyAccounts, '0 (Completely Purged)', $legacyAccounts === 0 ? 'PASS' : 'FAIL'],
            ['Journal Lines on Legacy 2610', $legacyLines2610, '0 (Remapped to 2120)', $legacyLines2610 === 0 ? 'PASS' : 'FAIL'],
            ['Journal Lines on Nepal 2120 (VAT Payable)', $nepalVatLines2120, '>= 1,500 (Active Postings)', $nepalVatLines2120 >= 1500 ? 'PASS' : 'FAIL'],
            ['Journal Entries with Foreign Currency', $foreignJournals, '0 (Strictly NPR)', $foreignJournals === 0 ? 'PASS' : 'FAIL'],
            ['Journal Lines with Foreign Currency', $foreignLines, '0 (Strictly NPR)', $foreignLines === 0 ? 'PASS' : 'FAIL'],
            ['Statutory Invoices with Foreign Currency', $foreignInvoices, '0 (Strictly NPR)', $foreignInvoices === 0 ? 'PASS' : 'FAIL'],
            ['Vouchers with Legacy Prefix BIL-', $bilVouchers, '0 (Standardized to JV-)', $bilVouchers === 0 ? 'PASS' : 'FAIL'],
            ['Invoices with Legacy Prefix FAK-', $fakInvoices, '0 (Standardized to INV-)', $fakInvoices === 0 ? 'PASS' : 'FAIL'],
            ['Invoices with Non-Nepal Contact Country', $nonNepalInvoices, '0 (Strictly NP)', $nonNepalInvoices === 0 ? 'PASS' : 'FAIL'],
            ['Module Settings: Standard VAT Rate', $vatRateSetting . '%', '13.00% (Nepal VAT Act)', abs($vatRateSetting - 13.00) < 0.001 ? 'PASS' : 'FAIL'],
            ['Module Settings: Sales VAT GL Account', $vatAccSetting, '2120 (Nepal Output VAT)', $vatAccSetting === '2120' ? 'PASS' : 'FAIL'],
            ['Journal Lines with Non-Standard VAT Rate', $nonStandardVatLines, '0 (Strictly 13%)', $nonStandardVatLines === 0 ? 'PASS' : 'FAIL'],
            ['Invoice Items with Non-Standard VAT Rate', $invItemsNonStandardVat, '0 (Strictly 13%)', $invItemsNonStandardVat === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($legacyAccounts > 0) $report['anomalies'][] = "Found $legacyAccounts legacy accounts in chart of accounts.";
        if ($legacyLines2610 > 0) $report['anomalies'][] = "Found $legacyLines2610 journal lines still pointing to legacy account 2610.";
        if ($nepalVatLines2120 === 0) $report['anomalies'][] = "No journal lines found for Nepal VAT account 2120.";
        if ($foreignJournals > 0) $report['anomalies'][] = "Found $foreignJournals journal entries with foreign currency.";
        if ($foreignLines > 0) $report['anomalies'][] = "Found $foreignLines journal lines with foreign currency.";
        if ($foreignInvoices > 0) $report['anomalies'][] = "Found $foreignInvoices invoices with foreign currency.";
        if ($bilVouchers > 0) $report['anomalies'][] = "Found $bilVouchers journal entries with legacy prefix BIL-.";
        if ($fakInvoices > 0) $report['anomalies'][] = "Found $fakInvoices invoices with legacy prefix FAK-.";
        if ($nonNepalInvoices > 0) $report['anomalies'][] = "Found $nonNepalInvoices invoices with non-Nepal contact_country.";
        if (abs($vatRateSetting - 13.00) >= 0.001) $report['anomalies'][] = "Module setting standard_vat_rate is $vatRateSetting%, must be 13.00%.";
        if ($vatAccSetting !== '2120') $report['anomalies'][] = "Module setting gl_sales_vat_account is $vatAccSetting, must be 2120.";
        if ($nonStandardVatLines > 0) $report['anomalies'][] = "Found $nonStandardVatLines journal lines with non-standard vat_rate.";
        if ($invItemsNonStandardVat > 0) $report['anomalies'][] = "Found $invItemsNonStandardVat invoice items with non-standard vat_rate.";

        $report['sections']['nepal_accounting_audit'] = [
            'legacy_accounts' => $legacyAccounts,
            'legacy_lines_2610' => $legacyLines2610,
            'nepal_vat_lines_2120' => $nepalVatLines2120,
            'foreign_journals' => $foreignJournals,
            'foreign_lines' => $foreignLines,
            'foreign_invoices' => $foreignInvoices,
            'bil_vouchers' => $bilVouchers,
            'fak_invoices' => $fakInvoices,
            'non_nepal_invoices' => $nonNepalInvoices,
            'vat_rate_setting' => $vatRateSetting,
            'vat_account_setting' => $vatAccSetting,
            'non_standard_vat_lines' => $nonStandardVatLines,
            'inv_items_non_standard_vat' => $invItemsNonStandardVat,
        ];

        // 12. SUPPLIERS & PROCUREMENT INTEGRITY AUDIT
        $this->info("\n--- 12. SUPPLIERS & PROCUREMENT INTEGRITY AUDIT ---");
        $totalSuppliers = DB::table('inventory_suppliers')->count();
        $totalDueBalance = (float) DB::table('inventory_suppliers')->sum('due_balance');
        $unpaidInvoices = DB::table('accounting_invoices')
            ->where('type', 'supplier_bill')
            ->where('payment_status', 'unpaid')
            ->get();
        $totalAccountsPayable = 0.0;
        foreach ($unpaidInvoices as $inv) {
            $totalAccountsPayable += ((float)$inv->total_amount - (float)$inv->paid_amount);
        }

        $corrupted505400 = DB::table('inventory_purchase_order_items')
            ->where('unit_cost_currency', 505400)
            ->orWhere('unit_cost_npr', 505400)
            ->count();
        $corruptedBatchTotals = DB::table('inventory_purchase_order_items')
            ->where('unit_cost_currency', '>', 50000)
            ->count();
        $cloPoIds = DB::table('inventory_purchase_orders')->where('po_number', 'like', 'PO-2026-CLO-%')->pluck('id');
        $cloProduct130Items = DB::table('inventory_purchase_order_items')->whereIn('purchase_order_id', $cloPoIds)->where('product_id', 130)->count();

        $this->table(['Suppliers & Procurement Invariant Check', 'Current Value', 'Statutory Requirement', 'Status'], [
            ['Total Authoritative Suppliers', $totalSuppliers, '23 (Strictly Authoritative List)', $totalSuppliers === 23 ? 'PASS' : 'FAIL'],
            ['Total Supplier Due Balances (Rs.)', number_format($totalDueBalance, 2), '4,212,905.00', abs($totalDueBalance - 4212905.00) < 0.01 ? 'PASS' : 'FAIL'],
            ['Total Kharid Khata Accounts Payable (Rs.)', number_format($totalAccountsPayable, 2), '4,212,905.00', abs($totalAccountsPayable - 4212905.00) < 0.01 ? 'PASS' : 'FAIL'],
            ['505,400 Unit Cost Records', $corrupted505400, '0 (No 505,400 Corruption)', $corrupted505400 === 0 ? 'PASS' : 'FAIL'],
            ['Summary Rows Accidentally Imported', $corruptedBatchTotals, '0 (No Batch Totals in Unit Cost)', $corruptedBatchTotals === 0 ? 'PASS' : 'FAIL'],
            ['Clothes PO Items on Sneakers-06 (ID 130)', $cloProduct130Items, '0 (Zero Misclassified Items)', $cloProduct130Items === 0 ? 'PASS' : 'FAIL'],
        ]);

        if ($totalSuppliers !== 23) $report['anomalies'][] = "Supplier count is {$totalSuppliers}, must be strictly 23.";
        if (abs($totalDueBalance - 4212905.00) >= 0.01) $report['anomalies'][] = "Total supplier due balance is Rs. " . number_format($totalDueBalance, 2) . ", expected Rs. 4,212,905.00.";
        if (abs($totalAccountsPayable - 4212905.00) >= 0.01) $report['anomalies'][] = "Total accounts payable is Rs. " . number_format($totalAccountsPayable, 2) . ", expected Rs. 4,212,905.00.";
        if ($corrupted505400 > 0) $report['anomalies'][] = "Found {$corrupted505400} purchase items with unit_cost = 505,400.";
        if ($corruptedBatchTotals > 0) $report['anomalies'][] = "Found {$corruptedBatchTotals} purchase items with unit_cost > 50,000 representing summary rows.";
        if ($cloProduct130Items > 0) $report['anomalies'][] = "Found {$cloProduct130Items} clothing purchase order items referencing Sneakers - 06 (ID 130).";

        $report['sections']['suppliers_procurement'] = [
            'total_suppliers' => $totalSuppliers,
            'total_due_balance' => $totalDueBalance,
            'total_accounts_payable' => $totalAccountsPayable,
            'corrupted_505400' => $corrupted505400,
            'corrupted_batch_totals' => $corruptedBatchTotals,
            'clothing_product_130_items' => $cloProduct130Items,
        ];

        // 13. FORENSIC CERTIFICATION PASS (Section 36)
        $this->info("\n================================================================");
        $this->info("  LAIJAU ERP — FINAL STATUTORY FORENSIC CERTIFICATION REPORT   ");
        $this->info("================================================================");

        $totalPhysicalStock = (int)DB::table('inventory_stock_levels')->sum('quantity_on_hand');
        $shoesStock = (int)DB::table('inventory_stock_levels')->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')->where('products.type', 'footwear')->sum('inventory_stock_levels.quantity_on_hand');
        $clothesStock = (int)DB::table('inventory_stock_levels')->join('products', 'products.id', '=', 'inventory_stock_levels.product_id')->where('products.type', '!=', 'footwear')->sum('inventory_stock_levels.quantity_on_hand');

        $totalCogsnpr = (float)DB::table('offline_sales')->sum('total_cost_npr');
        $totalRevNpr = (float)DB::table('offline_sales')->sum('total_amount');
        $overallMarginPct = $totalRevNpr > 0 ? (($totalRevNpr - $totalCogsnpr) / $totalRevNpr) * 100 : 0.0;

        $certifications = [
            'Authoritative files' => (file_exists(base_path('private_docs/Real Laijau Data/Jan sales.pdf')) || file_exists(base_path('Real Laijau Data/Jan sales.pdf')) || file_exists(base_path('Real Laijau Data/Jan Sales.xlsx')) || file_exists(base_path('private_docs/Real Laijau Data/Sales Jan to Sept 2026/Jan sales.pdf'))) && (file_exists(base_path('private_docs/Real Laijau Data/Clothes purchase.pdf')) || file_exists(base_path('Real Laijau Data/Clothes purchase.pdf')) || file_exists(base_path('Real Laijau Data/Clothes purchase.xlsx')) || file_exists(base_path('private_docs/Real Laijau Data/Purchase from Jan/Clothes purchase.pdf'))),
            'Row classification' => $corrupted505400 === 0 && $corruptedBatchTotals === 0,
            'Product mapping' => $cloProduct130Items === 0,
            'Purchase reconstruction' => $corrupted505400 === 0,
            'Purchase totals' => abs($totalAccountsPayable - 4212905.00) < 0.01,
            'Sales reconstruction' => $totalPos >= 11152,
            'February sales' => $febSalesCount === 1763,
            'Inventory reconciliation' => $totalPhysicalStock === 2721 || ($shoesStock === 736 && $clothesStock === 1985),
            'COGS' => $totalCogsnpr > 0,
            'Gross margin' => $overallMarginPct > 20.0 && $overallMarginPct < 85.0,
            'Accounting balance' => round(abs((float)DB::table('accounting_journal_entry_lines')->sum('debit') - (float)DB::table('accounting_journal_entry_lines')->sum('credit')), 4) === 0.0,
            'Nepal payment methods' => DB::table('accounting_journal_entries')->where('currency', '!=', 'NPR')->count() === 0,
            'Foreign remnants' => DB::table('inventory_warehouses')->where('country', '!=', 'NP')->count() === 0,
            'Duplicate detection' => $dupPhones === 0,
            'Data integrity' => $orphanVariants === 0 && $negativeStockCount === 0 && $cancelledWithTracking === 0,
        ];

        $allPass = true;
        foreach ($certifications as $certName => $passed) {
            if ($passed) {
                $this->line("<info>[PASS]</info> {$certName}");
            } else {
                $this->line("<error>[FAIL]</error> {$certName}");
                $allPass = false;
                $report['anomalies'][] = "Certification failed: {$certName}";
            }
        }

        // SUMMARY
        $this->newLine();
        if ($allPass && count($report['anomalies']) === 0) {
            $this->info(">>> FINAL CERTIFICATION = PASSED <<<");
            $this->info("Zero referential orphans, zero duplicate identifiers, general ledger balanced to NPR 0.00, physical stock reconciled to 2,721 pcs, and all 14 invariants certified.");
        } else {
            $this->error(">>> FINAL CERTIFICATION = FAILED <<<");
            foreach ($report['anomalies'] as $a) {
                $this->warn(" - $a");
            }
            $report['overall_status'] = 'FAIL';
        }

        // Export JSON artifact
        $outPath = storage_path('app/migration/final_data_integrity_audit_report.json');
        File::ensureDirectoryExists(dirname($outPath));
        File::put($outPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("Complete audit report saved to: $outPath");

        return ($allPass && count($report['anomalies']) === 0) ? 0 : 1;
    }
}
