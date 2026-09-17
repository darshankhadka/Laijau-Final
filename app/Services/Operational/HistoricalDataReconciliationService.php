<?php

declare(strict_types=1);

namespace App\Services\Operational;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HistoricalDataReconciliationService
{
    protected string $extractedDir;

    public function __construct()
    {
        $this->extractedDir = storage_path('app/migration/real_data_extracted');
    }

    /**
     * Load an extracted JSON file safely.
     */
    protected function loadJson(string $filename): array
    {
        $path = "{$this->extractedDir}/{$filename}";
        if (!File::exists($path)) {
            throw new \RuntimeException("Extracted data file not found: {$path}");
        }

        $decoded = json_decode(File::get($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Failed to parse JSON file: {$path}");
        }

        return $decoded;
    }

    /**
     * Audit all authoritative datasets and return health metrics.
     */
    public function auditAllSources(): array
    {
        $febData = $this->loadJson('feb_sales_2026.json');
        $cancelledData = $this->loadJson('cancelled_orders_2026.json');
        $clothesPurchases = $this->loadJson('clothes_purchases_2026.json');

        $febSales = $febData['sales'] ?? [];
        $febExpenses = $febData['expenses'] ?? [];

        $totalCash = array_sum(array_column($febSales, 'cash_amount'));
        $totalFonepay = array_sum(array_column($febSales, 'fonepay_amount'));
        $totalGross = array_sum(array_column($febSales, 'total_amount'));
        $totalExpenses = array_sum(array_column($febExpenses, 'amount'));
        $totalClothesCost = array_sum(array_column($clothesPurchases, 'total_cost'));

        // Current database state
        $existingFebSalesCount = DB::table('offline_sales')
            ->whereBetween('sold_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59'])
            ->count();

        $existingCancelledCount = DB::table('orders')
            ->where('order_number', 'like', 'CAN-2026-%')
            ->count();

        $existingClothesPoCount = DB::table('inventory_purchase_orders')
            ->where('po_number', 'like', 'PO-2026-CLO-%')
            ->count();

        $ncmShipmentsCount = DB::table('shipments')->count();

        return [
            'timestamp' => now()->toIso8601String(),
            'february_sales' => [
                'source_sales_count' => count($febSales),
                'source_cash_npr' => round($totalCash, 2),
                'source_fonepay_npr' => round($totalFonepay, 2),
                'source_gross_sales_npr' => round($totalGross, 2),
                'source_expenses_count' => count($febExpenses),
                'source_expenses_npr' => round($totalExpenses, 2),
                'database_existing_feb_sales' => $existingFebSalesCount,
                'is_already_imported' => ($existingFebSalesCount > 0),
            ],
            'cancelled_orders' => [
                'source_count' => count($cancelledData),
                'database_existing_can_orders' => $existingCancelledCount,
                'is_already_imported' => ($existingCancelledCount > 0),
            ],
            'clothes_procurement' => [
                'source_items_count' => count($clothesPurchases),
                'source_total_cost_npr' => round($totalClothesCost, 2),
                'database_existing_clo_pos' => $existingClothesPoCount,
                'is_already_imported' => ($existingClothesPoCount > 0),
            ],
            'ncm_logistics' => [
                'total_shipments' => $ncmShipmentsCount,
                'expected_invariant' => 1623,
                'invariant_held' => ($ncmShipmentsCount === 1623),
            ],
            'ledger_status' => $this->verifyLedgerBalance(),
        ];
    }

    /**
     * Full dry run across all streams.
     */
    public function dryRun(): array
    {
        return $this->executeReconciliation(true);
    }

    /**
     * Apply live reconciliation with full database transaction and rollback on failure.
     */
    public function apply(): array
    {
        return DB::transaction(function () {
            return $this->executeReconciliation(false);
        });
    }

    /**
     * Core execution engine for historical reconciliation.
     */
    protected function executeReconciliation(bool $dryRun = false): array
    {
        DB::disableQueryLog();

        $metrics = [
            'mode' => $dryRun ? 'DRY_RUN' : 'LIVE_APPLY',
            'february_sales_created' => 0,
            'february_sales_items_created' => 0,
            'february_sales_revenue_npr' => 0.0,
            'february_expenses_created' => 0,
            'february_expenses_total_npr' => 0.0,
            'cancelled_orders_created' => 0,
            'cancelled_order_items_created' => 0,
            'clothes_pos_created' => 0,
            'clothes_po_items_created' => 0,
            'clothes_po_cost_npr' => 0.0,
            'accounting_invoices_created' => 0,
            'journal_entries_created' => 0,
            'users_created' => 0,
            'ledger_balanced' => false,
            'ncm_invariant_verified' => false,
        ];

        // 1. RECONCILE FEBRUARY SHOWROOM SALES
        $febData = $this->loadJson('feb_sales_2026.json');
        $febSales = $febData['sales'] ?? [];
        $febExpenses = $febData['expenses'] ?? [];

        $existingFebCount = DB::table('offline_sales')
            ->whereBetween('sold_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59'])
            ->count();

        $showroomWh = Warehouse::where('code', 'STORE-KTM-01')->first()
            ?? Warehouse::firstOrCreate(
                ['code' => 'STORE-KTM-01'],
                ['name' => 'Laijau Showroom', 'type' => 'showroom_pos', 'is_default' => 0, 'country' => 'NP']
            );

        $defaultProduct = Product::find(130) ?? Product::first();

        if ($existingFebCount === 0) {
            $saleIndex = 1;
            foreach ($febSales as $s) {
                $refNumber = sprintf('OFF-2026-02-%05d', $saleIndex++);
                $totalAmt = (float)$s['total_amount'];
                $cashAmt = (float)$s['cash_amount'];
                $foneAmt = (float)$s['fonepay_amount'];

                $metrics['february_sales_created']++;
                $metrics['february_sales_revenue_npr'] += $totalAmt;

                if (!$dryRun) {
                    $sale = OfflineSale::create([
                        'sale_number' => $refNumber,
                        'warehouse_id' => $showroomWh->id,
                        'customer_name' => !empty($s['customer_name']) ? $s['customer_name'] : 'Walk-in Showroom Customer',
                        'customer_phone' => $s['customer_phone'] ?? null,
                        'currency' => 'NPR',
                        'subtotal' => $totalAmt,
                        'total_amount' => $totalAmt,
                        'cash_received' => $cashAmt,
                        'payment_method' => $s['payment_method'],
                        'sales_channel' => 'showroom_pos',
                        'status' => 'completed',
                        'sold_at' => $s['date'] . ' 14:00:00',
                        'staff_name' => 'Showroom Staff',
                    ]);

                    OfflineSaleItem::create([
                        'offline_sale_id' => $sale->id,
                        'product_id' => $defaultProduct?->id ?? 1,
                        'product_name' => $s['product_code'] ?: 'Showroom Item',
                        'sku' => $s['product_code'] ?: 'SHOWROOM-ITEM',
                        'size' => $s['size'] ?? null,
                        'quantity' => 1,
                        'unit_price' => $totalAmt,
                        'total_price' => $totalAmt,
                    ]);
                    $metrics['february_sales_items_created']++;

                    $taxableAmt = round($totalAmt / 1.13, 2);
                    $vatAmt = round($totalAmt - $taxableAmt, 2);

                    // Statutory Bikri Khata (Sales Book)
                    AccountingInvoice::create([
                        'invoice_number' => sprintf('BK-2026-02-%05d', $sale->id),
                        'type' => 'sales_invoice',
                        'fiscal_year' => '2082/83',
                        'contact_name' => $sale->customer_name,
                        'contact_phone' => $sale->customer_phone,
                        'customer_type' => 'walk_in_pos',
                        'sales_channel' => 'pos_showroom',
                        'issue_date' => $s['date'],
                        'currency' => 'NPR',
                        'subtotal' => $taxableAmt,
                        'taxable_amount' => $taxableAmt,
                        'vat_amount' => $vatAmt,
                        'total_amount' => $totalAmt,
                        'payment_status' => 'paid',
                        'reference_offline_sale_id' => $sale->id,
                        'posted_to_gl' => true,
                        'branch' => 'Durbar Marg, Kathmandu',
                    ]);
                    $metrics['accounting_invoices_created']++;

                    // Balanced Double-Entry Voucher
                    $this->createShowroomSaleVoucher($s['date'], $refNumber, $sale->id, $cashAmt, $foneAmt, $totalAmt);
                    $metrics['journal_entries_created']++;
                } else {
                    $metrics['february_sales_items_created']++;
                    $metrics['accounting_invoices_created']++;
                    $metrics['journal_entries_created']++;
                }
            }

            // February Showroom Expenses
            $expIndex = 1;
            foreach ($febExpenses as $exp) {
                $expAmt = (float)$exp['amount'];
                if ($expAmt <= 0) continue;

                $metrics['february_expenses_created']++;
                $metrics['february_expenses_total_npr'] += $expAmt;

                if (!$dryRun) {
                    $this->createBalancedVoucher(
                        $exp['date'],
                        "Showroom Cash Expense: {$exp['description']}",
                        'showroom_expense',
                        $expIndex++,
                        $expAmt,
                        78, // Account 78: 6200 Operational Expense
                        53  // Account 53: 1110 Cash on Hand
                    );
                    $metrics['journal_entries_created']++;
                } else {
                    $metrics['journal_entries_created']++;
                }
            }
        }

        // 2. RECONCILE CANCELLED ORDERS
        $cancelledData = $this->loadJson('cancelled_orders_2026.json');
        $existingCanCount = DB::table('orders')
            ->where('order_number', 'like', 'CAN-2026-%')
            ->count();

        if ($existingCanCount === 0) {
            $canIndex = 1;
            foreach ($cancelledData as $c) {
                $canNumber = sprintf('CAN-2026-%05d', $canIndex++);
                $metrics['cancelled_orders_created']++;

                if (!$dryRun) {
                    $nameParts = explode(' ', trim($c['customer_name'] ?: 'Online Customer'), 2);
                    $fName = $nameParts[0];
                    $lName = $nameParts[1] ?? 'Customer';

                    $orderDate = '2026-01-15 12:00:00';
                    if (!empty($c['date_raw'])) {
                        // Extract day from strings like '1st Jan', '2nd Jan'
                        preg_match('/(\d+)/', $c['date_raw'], $m);
                        if (!empty($m[1])) {
                            $day = (int)$m[1];
                            if ($day >= 1 && $day <= 31) {
                                $orderDate = sprintf('2026-01-%02d 12:00:00', $day);
                            }
                        }
                    }

                    $order = Order::create([
                        'order_number' => $canNumber,
                        'channel' => 'online',
                        'first_name' => $fName,
                        'last_name' => $lName,
                        'email' => !empty($c['phone']) ? "cust_{$c['phone']}@laijau.com" : "customer_can_{$canIndex}@laijau.com",
                        'phone' => $c['phone'] ?: null,
                        'shipping_address' => $c['shipping_address'] ?: 'Kathmandu Valley, Nepal',
                        'shipping_country' => 'Nepal',
                        'subtotal' => $c['subtotal'],
                        'shipping_fee' => $c['shipping_fee'],
                        'total_amount' => $c['total_amount'],
                        'currency' => 'NPR',
                        'carrier' => 'Nepal Can Move (NCM)',
                        'courier_name' => 'Nepal Can Move (NCM)',
                        'status' => 'cancelled',
                        'payment_status' => 'unpaid',
                        'payment_method' => 'cash_on_delivery',
                        'cancelled_at' => $orderDate,
                        'cancellation_reason' => $c['cancellation_reason'],
                        'created_at' => $orderDate,
                        'updated_at' => $orderDate,
                    ]);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $defaultProduct?->id ?? 1,
                        'product_name' => $c['product_name'] ?: 'Cancelled Product',
                        'sku' => $c['sku'] ?: 'CANCELLED-PROD',
                        'quantity' => 1,
                        'unit_price' => $c['subtotal'],
                        'selected_size' => $c['size'] ?? null,
                    ]);
                    $metrics['cancelled_order_items_created']++;

                    // Register Customer User if phone present and not in users table
                    if (!empty($c['phone'])) {
                        $userExists = User::where('phone', $c['phone'])->exists();
                        if (!$userExists) {
                            User::create([
                                'name' => $c['customer_name'] ?: 'Laijau Customer',
                                'email' => "cust_{$c['phone']}@laijau.com",
                                'phone' => $c['phone'],
                                'role' => 'customer',
                                'password' => Hash::make(Str::random(32)),
                                'country' => 'Nepal',
                                'notes' => 'Customer from historical cancelled orders dataset',
                                'created_at' => $orderDate,
                            ]);
                            $metrics['users_created']++;
                        }
                    }
                } else {
                    $metrics['cancelled_order_items_created']++;
                }
            }
        }

        // 3. RECONCILE CLOTHES PURCHASES
        $clothesPurchases = $this->loadJson('clothes_purchases_2026.json');
        $existingCloPoCount = DB::table('inventory_purchase_orders')
            ->where('po_number', 'like', 'PO-2026-CLO-%')
            ->count();

        $centralWh = Warehouse::where('code', 'WH-KTM-MAIN')->first()
            ?? Warehouse::firstOrCreate(
                ['code' => 'WH-KTM-MAIN'],
                ['name' => 'Laijau Central Fulfillment Hub', 'type' => 'warehouse', 'is_default' => 1, 'country' => 'NP']
            );

        // Group clothes purchases by date and factory
        if ($existingCloPoCount === 0) {
            $batches = [];
            foreach ($clothesPurchases as $cp) {
                $batchKey = ($cp['date'] ?? '2026-02-15') . '|' . strtoupper(trim($cp['factory'] ?? 'GARMENT'));
                $batches[$batchKey]['date'] = $cp['date'] ?? '2026-02-15';
                $batches[$batchKey]['factory'] = $cp['factory'] ?? 'Garment Supplier';
                $batches[$batchKey]['items'][] = $cp;
            }

            // Find or map suppliers
            $kavishSupplier = Supplier::where('tax_vat_number', '609631237')->orWhere('name', 'like', '%Kavish%')->first();
            $defaultGarmentSupplier = Supplier::where('name', 'like', '%Apparel%')->orWhere('name', 'like', '%Store%')->first()
                ?? Supplier::first();

            $cloPoIndex = 1;
            foreach ($batches as $batchKey => $b) {
                $poNumber = sprintf('PO-2026-CLO-%04d', $cloPoIndex++);
                $batchTotal = array_sum(array_column($b['items'], 'total_cost'));

                $supplier = null;
                if (stripos($b['factory'], 'kavish') !== false) {
                    $supplier = $kavishSupplier;
                } else {
                    $supplier = $defaultGarmentSupplier;
                }

                $metrics['clothes_pos_created']++;
                $metrics['clothes_po_cost_npr'] += $batchTotal;

                if (!$dryRun && $supplier) {
                    $po = PurchaseOrder::create([
                        'po_number' => $poNumber,
                        'supplier_id' => $supplier->id,
                        'warehouse_id' => $centralWh->id,
                        'order_date' => $b['date'],
                        'status' => 'received',
                        'currency' => 'NPR',
                        'subtotal_currency' => $batchTotal,
                        'total_amount_npr' => $batchTotal,
                        'notes' => "Historical clothes procurement batch: {$b['factory']}",
                    ]);

                    foreach ($b['items'] as $item) {
                        $art = strtolower(trim(($item['article'] ?? '') . ' ' . ($item['code'] ?? '')));
                        $targetSku = 'LAI-TSH-001';
                        if (str_contains($art, 'moja') || str_contains($art, 'socks') || str_contains($art, 'sock')) {
                            $targetSku = 'LAI-ACC-SCK01';
                        } elseif (str_contains($art, 'penty') || str_contains($art, 'underwear') || str_contains($art, 'underware') || str_contains($art, 'boxer') || str_contains($art, 'bra') || str_contains($art, 'machoo')) {
                            $targetSku = 'LAI-ACC-UND01';
                        } elseif (str_contains($art, 'hat') || str_contains($art, 'topi') || str_contains($art, 'cap')) {
                            $targetSku = 'LAI-ACC-HAT01';
                        } elseif (str_contains($art, 'jacket') || str_contains($art, 'outer') || str_contains($art, 'windcheater') || str_contains($art, 'windshiter')) {
                            $targetSku = 'LAI-JKT-001';
                        } elseif (str_contains($art, 'trouser') || str_contains($art, 'pant') || str_contains($art, 'jogger')) {
                            $targetSku = 'LAI-TRS-001';
                        } elseif (str_contains($art, 'hoodie') || str_contains($art, 'sweat') || str_contains($art, 'sweater')) {
                            $targetSku = 'LAI-HOD-001';
                        }
                        $targetProduct = Product::where('sku', $targetSku)->first() ?? Product::where('sku', 'LAI-TSH-001')->first() ?? $defaultProduct;
                        $rawCost = (float)$item['unit_cost'];
                        $finalUnitCost = ($rawCost < 500.00) ? (float)($targetProduct?->price ?? 500.00) : $rawCost;
                        $finalTotalCost = $finalUnitCost * (int)$item['quantity'];

                        PurchaseOrderItem::create([
                            'purchase_order_id' => $po->id,
                            'product_id' => $targetProduct?->id ?? 1,
                            'quantity_ordered' => $item['quantity'],
                            'quantity_received' => $item['quantity'],
                            'unit_cost_currency' => $finalUnitCost,
                            'unit_cost_npr' => $finalUnitCost,
                            'total_cost_npr' => $finalTotalCost,
                        ]);
                        $metrics['clothes_po_items_created']++;
                    }

                    // Statutory Kharid Khata (Purchase Book)
                    AccountingInvoice::create([
                        'invoice_number' => sprintf('KH-2026-CLO-%04d', $po->id),
                        'type' => 'supplier_bill',
                        'fiscal_year' => '2082/83',
                        'contact_name' => $b['factory'],
                        'purchase_type' => 'local_taxable_13',
                        'sales_channel' => 'pos_showroom',
                        'issue_date' => $b['date'],
                        'currency' => 'NPR',
                        'subtotal' => $batchTotal,
                        'total_amount' => $batchTotal,
                        'payment_status' => 'paid',
                        'reference_purchase_order_id' => $po->id,
                        'posted_to_gl' => true,
                        'notes' => "Kharid Khata entry for historical clothes purchase batch ({$b['factory']})",
                    ]);
                    $metrics['accounting_invoices_created']++;

                    // Balanced Double-Entry: Debit Merchandise Inventory (31), Credit Operating Bank (54)
                    $this->createBalancedVoucher(
                        $b['date'],
                        "Clothes Purchase Order {$poNumber} - {$b['factory']}",
                        'purchase_order',
                        $po->id,
                        $batchTotal,
                        31, // Account 31: 2210 Merchandise Inventory
                        54  // Account 54: 1120 Operating Bank Account
                    );
                    $metrics['journal_entries_created']++;
                } else {
                    $metrics['clothes_po_items_created'] += count($b['items']);
                    $metrics['accounting_invoices_created']++;
                    $metrics['journal_entries_created']++;
                }
            }
        }

        // Post-validation
        $ledger = $this->verifyLedgerBalance();
        $metrics['ledger_balanced'] = $ledger['is_balanced'];
        $metrics['ledger_debit_sum'] = $ledger['total_debit'];
        $metrics['ledger_credit_sum'] = $ledger['total_credit'];
        $metrics['ledger_variance'] = $ledger['variance'];

        $ncm = $this->verifyNcmLogisticsInvariants();
        $metrics['ncm_invariant_verified'] = $ncm['all_invariants_pass'];

        return $metrics;
    }

    /**
     * Create double-entry journal entry for showroom sale supporting cash, fonepay, and split tender.
     */
    protected function createShowroomSaleVoucher(
        string $date,
        string $saleNumber,
        int $saleId,
        float $cashAmt,
        float $foneAmt,
        float $totalAmt
    ): JournalEntry {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-POS-' . Str::upper(Str::random(10)),
            'voucher_date' => $date,
            'entry_type' => 'manual',
            'reference_type' => 'offline_sale',
            'reference_id' => $saleId,
            'description' => "Showroom POS Sale {$saleNumber}",
            'currency' => 'NPR',
            'total_debit' => $totalAmt,
            'total_credit' => $totalAmt,
            'is_balanced' => true,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        $lineNum = 1;
        // Debit Cash
        if ($cashAmt > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => 53, // 1110 Cash on Hand
                'account_number' => '1110',
                'line_number' => $lineNum++,
                'debit' => $cashAmt,
                'credit' => 0.0,
                'amount_currency' => $cashAmt,
                'currency' => 'NPR',
                'description' => "Cash collected for sale {$saleNumber}",
            ]);
        }

        // Debit FonePay / Digital Clearing
        if ($foneAmt > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => 58, // 1160 POS Card / Fonepay Clearing
                'account_number' => '1160',
                'line_number' => $lineNum++,
                'debit' => $foneAmt,
                'credit' => 0.0,
                'amount_currency' => $foneAmt,
                'currency' => 'NPR',
                'description' => "FonePay QR settlement for sale {$saleNumber}",
            ]);
        }

        // Credit Retail Sales Revenue
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => 65, // 4110 POS Sales Revenue
            'account_number' => '4110',
            'line_number' => $lineNum,
            'debit' => 0.0,
            'credit' => $totalAmt,
            'amount_currency' => $totalAmt,
            'currency' => 'NPR',
            'description' => "Revenue recognized for sale {$saleNumber}",
        ]);

        return $entry;
    }

    /**
     * Create a balanced two-line double-entry voucher.
     */
    protected function createBalancedVoucher(
        string $date,
        string $description,
        string $refType,
        int $refId,
        float $amount,
        int $debitAccountId,
        int $creditAccountId
    ): JournalEntry {
        static $accountsMap = null;
        if ($accountsMap === null) {
            $accountsMap = DB::table('accounting_accounts')->pluck('account_number', 'id')->all();
        }

        $entry = JournalEntry::create([
            'entry_number' => 'JV-HIST-' . Str::upper(Str::random(10)),
            'voucher_date' => $date,
            'entry_type' => 'manual',
            'reference_type' => $refType,
            'reference_id' => $refId,
            'description' => $description,
            'currency' => 'NPR',
            'total_debit' => $amount,
            'total_credit' => $amount,
            'is_balanced' => true,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        // Debit Line
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $debitAccountId,
            'account_number' => $accountsMap[$debitAccountId] ?? '1110',
            'line_number' => 1,
            'debit' => $amount,
            'credit' => 0.0,
            'amount_currency' => $amount,
            'currency' => 'NPR',
            'description' => $description . ' [Debit]',
        ]);

        // Credit Line
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $creditAccountId,
            'account_number' => $accountsMap[$creditAccountId] ?? '4110',
            'line_number' => 2,
            'debit' => 0.0,
            'credit' => $amount,
            'amount_currency' => $amount,
            'currency' => 'NPR',
            'description' => $description . ' [Credit]',
        ]);

        return $entry;
    }

    /**
     * Verify complete double-entry general ledger balance.
     */
    public function verifyLedgerBalance(): array
    {
        $totals = DB::table('accounting_journal_entry_lines')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $deb = (float)($totals->total_debit ?? 0.0);
        $crd = (float)($totals->total_credit ?? 0.0);
        $diff = abs($deb - $crd);

        return [
            'total_debit' => round($deb, 4),
            'total_credit' => round($crd, 4),
            'variance' => round($diff, 4),
            'is_balanced' => ($diff < 0.01),
        ];
    }

    /**
     * Verify NCM logistics invariants (must remain 100% compliant).
     */
    public function verifyNcmLogisticsInvariants(): array
    {
        $totalShipments = DB::table('shipments')->count();
        $matchedShipments = DB::table('shipments')->whereNotNull('order_id')->count();
        $ambiguousShipments = DB::table('shipments')->where('match_status', 'ambiguous')->count();
        $unmatchedShipments = DB::table('shipments')->where('match_status', 'unmatched')->count();

        // Check if any ambiguous or unmatched shipment has an order_id assigned
        $invalidAmbiguous = DB::table('shipments')
            ->whereIn('match_status', ['ambiguous', 'unmatched'])
            ->whereNotNull('order_id')
            ->count();

        // Check if all matched orders satisfy the strict tracking invariant
        $trackingMismatches = DB::table('shipments as s')
            ->join('orders as o', 's.order_id', '=', 'o.id')
            ->where(function ($q) {
                $q->whereColumn('s.external_tracking_number', '!=', 'o.tracking_number')
                    ->orWhereColumn('s.external_tracking_number', '!=', 'o.courier_order_id');
            })
            ->count();

        $allPass = ($totalShipments === 1623)
            && ($matchedShipments === 1332)
            && ($ambiguousShipments === 267)
            && ($unmatchedShipments === 24)
            && ($invalidAmbiguous === 0)
            && ($trackingMismatches === 0);

        return [
            'total_shipments' => $totalShipments,
            'matched_shipments' => $matchedShipments,
            'ambiguous_shipments' => $ambiguousShipments,
            'unmatched_shipments' => $unmatchedShipments,
            'invalid_ambiguous_assignments' => $invalidAmbiguous,
            'tracking_mismatches' => $trackingMismatches,
            'all_invariants_pass' => $allPass,
        ];
    }
}
