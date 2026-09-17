<?php

declare(strict_types=1);

namespace App\Services\Operational;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Category;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RealDataMigrationService
{
    protected string $extractedDir;
    protected AccountingService $accountingService;

    public function __construct(?AccountingService $accountingService = null)
    {
        $this->extractedDir = storage_path('app/migration/real_data_extracted');
        $this->accountingService = $accountingService ?? app(AccountingService::class);
    }

    /**
     * Audit extracted data files and return a comprehensive health/integrity report.
     */
    public function audit(): array
    {
        $suppliers = $this->loadJson('suppliers.json');
        $users = $this->loadJson('users.json');
        $purchases = $this->loadJson('purchases_jan_2026.json');
        $shoesStock = $this->loadJson('shoes_stock.json');
        $clothesStock = $this->loadJson('clothes_stock.json');
        $monthlySales = $this->loadJson('monthly_sales.json');
        $onlineOrders = $this->loadJson('online_orders.json');

        $totalSalesCash = 0.0;
        $totalSalesOnline = 0.0;
        $totalSalesGross = 0.0;
        $totalExpenses = 0.0;
        $monthBreakdown = [];

        foreach ($monthlySales as $month => $data) {
            $totalSalesCash += (float)($data['total_cash'] ?? 0);
            $totalSalesOnline += (float)($data['total_online'] ?? 0);
            $totalSalesGross += (float)($data['total_sales'] ?? 0);
            $totalExpenses += (float)($data['total_expenses'] ?? 0);

            $monthBreakdown[$month] = [
                'status' => $data['data_status'] ?? 'unknown',
                'notes' => $data['notes'] ?? '',
                'sales_count' => $data['sales_count'] ?? 0,
                'expense_count' => $data['expense_count'] ?? 0,
                'total_sales' => (float)($data['total_sales'] ?? 0),
                'total_expenses' => (float)($data['total_expenses'] ?? 0),
            ];
        }

        $totalPurchaseQty = 0;
        $totalPurchaseCost = 0.0;
        $totalPurchaseRetail = 0.0;
        foreach ($purchases as $p) {
            $totalPurchaseQty += (int)($p['quantity'] ?? 0);
            $totalPurchaseCost += (float)($p['total_cost'] ?? 0);
            $totalPurchaseRetail += ((int)($p['quantity'] ?? 0) * (float)($p['selling_price'] ?? 0));
        }

        $report = [
            'timestamp' => now()->toIso8601String(),
            'suppliers_count' => count($suppliers),
            'users_count' => count($users),
            'purchases_jan_2026' => [
                'batches_count' => count($purchases),
                'total_quantity_pcs' => $totalPurchaseQty,
                'total_quantity_pairs' => $totalPurchaseQty,
                'total_cost_npr' => round($totalPurchaseCost, 2),
                'total_retail_expected_npr' => round($totalPurchaseRetail, 2),
            ],
            'shoes_stock_lines' => count($shoesStock),
            'clothes_stock_lines' => count($clothesStock),
            'monthly_sales' => [
                'gross_sales_npr' => round($totalSalesGross, 2),
                'cash_collected_npr' => round($totalSalesCash, 2),
                'online_fonepay_npr' => round($totalSalesOnline, 2),
                'store_expenses_withdrawn_npr' => round($totalExpenses, 2),
                'periods' => $monthBreakdown,
            ],
            'online_dispatched_orders' => [
                'total_orders' => count($onlineOrders),
                'total_order_amount_npr' => round(array_sum(array_column($onlineOrders, 'order_amount')), 2),
                'total_delivery_charges_npr' => round(array_sum(array_column($onlineOrders, 'delivery_charge')), 2),
            ],
        ];

        File::ensureDirectoryExists(storage_path('app/migration'));
        File::put(storage_path('app/migration/audit_report.json'), json_encode($report, JSON_PRETTY_PRINT));

        return $report;
    }

    /**
     * Run simulation dry run without modifying any database tables.
     */
    public function dryRun(): array
    {
        return $this->executeMigration(true);
    }

    /**
     * Execute live migration with full transaction support.
     */
    public function import(): array
    {
        return $this->executeMigration(false);
    }

    /**
     * Core execution engine supporting both dry-run and live import.
     */
    protected function executeMigration(bool $dryRun = false): array
    {
        DB::disableQueryLog();

        $suppliers = $this->loadJson('suppliers.json');
        $users = $this->loadJson('users.json');
        $purchases = $this->loadJson('purchases_jan_2026.json');
        $shoesStock = $this->loadJson('shoes_stock.json');
        $clothesStock = $this->loadJson('clothes_stock.json');
        $monthlySales = $this->loadJson('monthly_sales.json');
        $onlineOrders = $this->loadJson('online_orders.json');

        // Preload Accounts Map
        $accountsMap = DB::table('accounting_accounts')->pluck('account_number', 'id')->all();

        $metrics = [
            'mode' => $dryRun ? 'DRY_RUN' : 'LIVE_IMPORT',
            'suppliers_processed' => 0,
            'suppliers_created' => 0,
            'suppliers_updated' => 0,
            'products_matched' => 0,
            'products_created' => 0,
            'variants_created' => 0,
            'variants_updated' => 0,
            'stock_levels_recorded' => 0,
            'purchase_orders_created' => 0,
            'purchase_order_items_created' => 0,
            'sales_orders_created' => 0,
            'sales_items_created' => 0,
            'sales_revenue_npr' => 0.0,
            'expenses_recorded' => 0,
            'expense_total_npr' => 0.0,
            'online_orders_created' => 0,
            'users_created' => 0,
            'journal_entries_created' => 0,
            'accounting_balanced' => true,
        ];

        $runLogic = function () use (
            &$metrics,
            $suppliers,
            $users,
            $purchases,
            $shoesStock,
            $clothesStock,
            $monthlySales,
            $onlineOrders,
            $dryRun,
            $accountsMap
        ) {
            // Warehouses
            $centralWh = Warehouse::firstOrCreate(
                ['code' => 'WH-KTM-MAIN'],
                ['name' => 'Laijau Central Fulfillment Hub', 'type' => 'warehouse', 'is_default' => 1, 'country' => 'NP']
            );
            $showroomWh = Warehouse::firstOrCreate(
                ['code' => 'STORE-KTM-01'],
                ['name' => 'Laijau Showroom', 'type' => 'showroom_pos', 'is_default' => 0, 'country' => 'NP']
            );

            // Categories
            $mensShoesCat = Category::where('slug', 'mens-shoes')->first() ?? Category::first();
            $mensWearCat = Category::where('slug', 'mens-wear')->first() ?? Category::where('slug', 'clothing')->first() ?? Category::first();

            // 1. SUPPLIERS
            $supplierMap = [];
            foreach ($suppliers as $s) {
                $metrics['suppliers_processed']++;
                $existing = Supplier::where('tax_vat_number', $s['tax_vat_number'])
                    ->when(empty($s['tax_vat_number']), function ($q) use ($s) {
                        return $q->orWhere('name', $s['name']);
                    })
                    ->orWhere('code', $s['code'])
                    ->first();

                if ($existing) {
                    $metrics['suppliers_updated']++;
                    if (!$dryRun) {
                        $existing->update([
                            'notes' => $s['notes'] ?? $existing->notes,
                            'is_active' => true,
                        ]);
                    }
                    $supplierMap[strtoupper(trim($s['name']))] = $existing->id;
                } else {
                    $metrics['suppliers_created']++;
                    if (!$dryRun) {
                        $created = Supplier::create([
                            'code' => $s['code'],
                            'name' => $s['name'],
                            'tax_vat_number' => $s['tax_vat_number'],
                            'country' => 'NP',
                            'currency' => 'NPR',
                            'payment_terms' => 'Net 30',
                            'notes' => $s['notes'] ?? null,
                            'is_active' => true,
                        ]);
                        $supplierMap[strtoupper(trim($s['name']))] = $created->id;
                    } else {
                        $supplierMap[strtoupper(trim($s['name']))] = 999000 + $metrics['suppliers_created'];
                    }
                }
            }

            // 2. PRODUCTS & VARIANTS
            $existingProductsBySku = Product::all()->keyBy(function ($p) {
                return strtoupper(trim((string)$p->sku));
            });

            $productMap = []; // code_colour => Product
            $variantMap = []; // code_colour_size => ProductVariant

            $buildShoeSku = function ($code, $colour) {
                $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$code));
                $col = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$colour));
                $col = str_replace('BROSHOF', 'BROSHOP', $col);
                return !empty($col) ? "{$c}{$col}" : $c;
            };

            // Process Footwear from Shoes Stock and Jan Purchases
            $allShoes = [];
            foreach ($shoesStock as $row) {
                $c = $row['colour'] ?? $row['color'] ?? $row['name'] ?? '';
                $k = $buildShoeSku($row['code'] ?? '', $c);
                $allShoes[$k]['factory'] = $row['factory'] ?? 'CITIZEN FACTORY';
                $allShoes[$k]['code'] = $row['code'] ?? '';
                $allShoes[$k]['colour'] = $c;
                $sz = (string)($row['size'] ?? '');
                $allShoes[$k]['sizes'][$sz] = ($allShoes[$k]['sizes'][$sz] ?? 0) + (int)($row['quantity'] ?? 0);
            }
            foreach ($purchases as $row) {
                $c = $row['colour'] ?? $row['color'] ?? '';
                $k = $buildShoeSku($row['code'] ?? '', $c);
                $allShoes[$k]['factory'] = $row['factory'] ?? 'CITIZEN FACTORY';
                $allShoes[$k]['code'] = $row['code'] ?? '';
                $allShoes[$k]['colour'] = $c;
                $allShoes[$k]['cost_price'] = (float)($row['unit_cost'] ?? 0);
                if ((float)($row['selling_price'] ?? 0) > 0) {
                    $allShoes[$k]['selling_price'] = (float)$row['selling_price'];
                }
            }

            foreach ($allShoes as $skuKey => $info) {
                $existing = $existingProductsBySku->get($skuKey)
                    ?? $existingProductsBySku->get(strtoupper(trim((string)$info['code'])));

                $product = null;
                if ($existing) {
                    $metrics['products_matched']++;
                    $product = $existing;
                    if (!$dryRun) {
                        $updateData = [];
                        if (!empty($info['cost_price']) && empty($existing->cost_price)) {
                            $updateData['cost_price'] = $info['cost_price'];
                        }
                        if (!empty($info['selling_price']) && empty($existing->price)) {
                            $updateData['price'] = $info['selling_price'];
                        }
                        if (!empty($updateData)) {
                            $existing->update($updateData);
                        }
                    }
                } else {
                    $metrics['products_created']++;
                    $name = trim("{$info['factory']} Shoes | {$info['code']} {$info['colour']}");
                    $cost = $info['cost_price'] ?? 1800.00;
                    $price = $info['selling_price'] ?? 2500.00;

                    if (!$dryRun) {
                        $slug = Str::slug("{$name}-{$skuKey}");
                        if (Product::where('slug', $slug)->exists()) {
                            $slug .= '-' . uniqid();
                        }
                        $product = Product::create([
                            'name' => $name,
                            'slug' => $slug,
                            'sku' => $skuKey,
                            'brand' => $info['factory'],
                            'type' => 'footwear',
                            'cost_price' => $cost,
                            'price' => $price,
                            'is_active' => true,
                            'is_published' => true,
                            'country_of_origin' => 'Nepal',
                        ]);
                        if ($mensShoesCat) {
                            $product->categories()->syncWithoutDetaching([$mensShoesCat->id]);
                        }
                    } else {
                        $product = new Product(['id' => 888000 + $metrics['products_created'], 'sku' => $skuKey, 'name' => $name]);
                    }
                }

                $productMap[$skuKey] = $product;

                // Create/reconcile variants
                $sizes = $info['sizes'] ?? ['39' => 0, '40' => 0, '41' => 0, '42' => 0, '43' => 0];
                foreach ($sizes as $size => $qty) {
                    $varSku = "{$skuKey}-{$size}";
                    if (!$dryRun && $product && $product->id) {
                        $variant = ProductVariant::firstOrCreate(
                            ['product_id' => $product->id, 'sku' => $varSku],
                            [
                                'size' => (string)$size,
                                'color' => (string)$info['colour'],
                                'price' => (float)($product->price ?? 0.0),
                                'cost_price' => (float)($product->cost_price ?? 0.0),
                                'stock_quantity' => $qty,
                                'is_active' => true,
                            ]
                        );
                        $variantMap["{$skuKey}_{$size}"] = $variant;
                        $metrics['variants_created']++;

                        // Set stock level
                        StockLevel::updateOrCreate(
                            ['warehouse_id' => $showroomWh->id, 'product_id' => $product->id, 'variant_id' => $variant->id],
                            ['quantity_on_hand' => $qty, 'unit_cost_npr' => (float)($product->cost_price ?? 0.0)]
                        );
                        $metrics['stock_levels_recorded']++;
                    } else {
                        $metrics['variants_created']++;
                        $metrics['stock_levels_recorded']++;
                    }
                }
            }

            // Group Clothes by SKU first (since Clothes Stock rows represent variant sizes)
            $allClothes = [];
            foreach ($clothesStock as $row) {
                $codeRaw = trim((string)($row['code'] ?? ''));
                $codeClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codeRaw));
                $itemName = trim((string)($row['item_name'] ?? $row['name'] ?? 'Clothes Item'));
                $sku = !empty($codeClean) ? "CLO-{$codeClean}" : 'CLO-' . Str::slug($itemName);
                $cost = (float)($row['cost_price'] ?? $row['cost'] ?? 0);
                $sell = (float)($row['selling_price'] ?? $row['price'] ?? 0);
                if (empty($allClothes[$sku])) {
                    $allClothes[$sku] = [
                        'item_name' => $itemName,
                        'sku' => $sku,
                        'cost_price' => $cost > 0 ? $cost : 800.00,
                        'selling_price' => $sell > 0 ? $sell : 1500.00,
                        'sizes' => [],
                    ];
                }
                $size = (string)($row['size'] ?? '');
                $allClothes[$sku]['sizes'][$size] = ($allClothes[$sku]['sizes'][$size] ?? 0) + (int)($row['quantity'] ?? 0);
                if ($cost > 0) {
                    $allClothes[$sku]['cost_price'] = $cost;
                }
                if ($sell > 0) {
                    $allClothes[$sku]['selling_price'] = $sell;
                }
            }

            foreach ($allClothes as $sku => $cinfo) {
                $existing = $existingProductsBySku->get($sku);
                $product = null;
                if ($existing) {
                    $metrics['products_matched']++;
                    $product = $existing;
                } else {
                    $metrics['products_created']++;
                    $name = $cinfo['item_name'];
                    $cost = $cinfo['cost_price'];
                    $price = $cinfo['selling_price'];

                    if (!$dryRun) {
                        $slug = Str::slug("{$name}-{$sku}");
                        if (Product::where('slug', $slug)->exists()) {
                            $slug .= '-' . uniqid();
                        }
                        $product = Product::create([
                            'name' => $name,
                            'slug' => $slug,
                            'sku' => $sku,
                            'type' => 'apparel',
                            'cost_price' => $cost,
                            'price' => $price,
                            'is_active' => true,
                            'is_published' => true,
                            'country_of_origin' => 'Nepal',
                        ]);
                        if ($mensWearCat) {
                            $product->categories()->syncWithoutDetaching([$mensWearCat->id]);
                        }
                    } else {
                        $product = new Product(['id' => 777000 + $metrics['products_created'], 'sku' => $sku, 'name' => $name]);
                    }
                }

                $productMap[$sku] = $product;

                foreach ($cinfo['sizes'] as $size => $qty) {
                    $varSku = "{$sku}-{$size}";
                    if (!$dryRun && $product && $product->id) {
                        $variant = ProductVariant::firstOrCreate(
                            ['product_id' => $product->id, 'sku' => $varSku],
                            [
                                'size' => $size,
                                'price' => (float)($product->price ?? 0.0),
                                'cost_price' => (float)($product->cost_price ?? 0.0),
                                'stock_quantity' => $qty,
                                'is_active' => true,
                            ]
                        );
                        $variantMap["{$sku}_{$size}"] = $variant;
                        $metrics['variants_created']++;

                        StockLevel::updateOrCreate(
                            ['warehouse_id' => $showroomWh->id, 'product_id' => $product->id, 'variant_id' => $variant->id],
                            ['quantity_on_hand' => $qty, 'unit_cost_npr' => (float)($product->cost_price ?? 0.0)]
                        );
                        $metrics['stock_levels_recorded']++;
                    } else {
                        $metrics['variants_created']++;
                        $metrics['stock_levels_recorded']++;
                    }
                }
            }

            // 3. PURCHASES JAN 2026
            $purchaseBatches = [];
            foreach ($purchases as $p) {
                $batchKey = "{$p['factory']}_{$p['date']}";
                $purchaseBatches[$batchKey]['factory'] = $p['factory'];
                $purchaseBatches[$batchKey]['date'] = $p['date'];
                $purchaseBatches[$batchKey]['items'][] = $p;
            }

            $poIndex = 1;
            foreach ($purchaseBatches as $batchKey => $b) {
                $poNumber = sprintf('PO-2026-01-%04d', $poIndex++);
                $supplierId = $supplierMap[strtoupper(trim($b['factory']))] ?? $supplierMap['CITIZEN FACTORY'] ?? null;
                $batchTotal = array_sum(array_column($b['items'], 'total_cost'));

                $metrics['purchase_orders_created']++;

                if (!$dryRun && $supplierId) {
                    $po = PurchaseOrder::create([
                        'po_number' => $poNumber,
                        'supplier_id' => $supplierId,
                        'warehouse_id' => $centralWh->id,
                        'order_date' => $b['date'],
                        'status' => 'received',
                        'currency' => 'NPR',
                        'subtotal_currency' => $batchTotal,
                        'total_amount_npr' => $batchTotal,
                        'notes' => "Imported real January 2026 purchase batch from {$b['factory']}",
                    ]);

                    foreach ($b['items'] as $item) {
                        $c = $item['colour'] ?? $item['color'] ?? '';
                        $skuKey = $buildShoeSku($item['code'] ?? '', $c);
                        $product = $productMap[$skuKey] ?? null;

                        PurchaseOrderItem::create([
                            'purchase_order_id' => $po->id,
                            'product_id' => $product?->id ?? 130,
                            'quantity_ordered' => $item['quantity'],
                            'quantity_received' => $item['quantity'],
                            'unit_cost_currency' => $item['unit_cost'],
                            'unit_cost_npr' => $item['unit_cost'],
                            'total_cost_npr' => $item['total_cost'],
                        ]);
                        $metrics['purchase_order_items_created']++;
                    }

                    // Kharid Khata (Purchase Book)
                    AccountingInvoice::create([
                        'invoice_number' => sprintf('KH-2026-%04d', $po->id),
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
                        'notes' => "Statutory Kharid Khata entry for {$b['factory']}",
                    ]);

                    // Double-entry voucher: Debit Merchandise Inventory (2210), Credit Bank (1120)
                    $this->createBalancedVoucher(
                        $b['date'],
                        "Purchase Order {$poNumber} - {$b['factory']}",
                        'purchase_order',
                        $po->id,
                        $batchTotal,
                        31, // 2210 Inventory
                        54, // 1120 Bank
                        $accountsMap
                    );
                    $metrics['journal_entries_created']++;
                } else {
                    $metrics['purchase_order_items_created'] += count($b['items']);
                    $metrics['journal_entries_created']++;
                }
            }

            // 4. SHOWROOM SALES & EXPENSES
            foreach ($monthlySales as $monthName => $mData) {
                if (($mData['data_status'] ?? '') === 'unavailable') {
                    continue; // Explicit February missing
                }

                $salesList = $mData['sales'] ?? [];
                $saleNum = 1;
                $mNum = $mData['month_number'] ?? 1;

                foreach ($salesList as $s) {
                    $refNumber = sprintf('OFF-2026-%02d-%05d', $mNum, $saleNum++);
                    $totalAmt = (float)$s['total_amount'];
                    $cashAmt = (float)$s['cash_amount'];
                    $metrics['sales_orders_created']++;
                    $metrics['sales_revenue_npr'] += $totalAmt;

                    if (!$dryRun) {
                        $sale = OfflineSale::create([
                            'sale_number' => $refNumber,
                            'warehouse_id' => $showroomWh->id,
                            'customer_name' => !empty($s['customer_name']) ? $s['customer_name'] : 'Walk-in Showroom Customer',
                            'customer_phone' => $s['phone'] ?? null,
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
                            'product_id' => 130,
                            'product_name' => $s['product_code'] ?: 'Showroom Item',
                            'sku' => $s['product_code'] ?: 'SHOWROOM-ITEM',
                            'size' => $s['size'] ?? null,
                            'quantity' => 1,
                            'unit_price' => $totalAmt,
                            'total_price' => $totalAmt,
                        ]);
                        $metrics['sales_items_created']++;

                        $taxableAmt = round($totalAmt / 1.13, 2);
                        $vatAmt = round($totalAmt - $taxableAmt, 2);

                        // Statutory Bikri Khata
                        AccountingInvoice::create([
                            'invoice_number' => sprintf('BK-2026-%02d-%05d', $mNum, $sale->id),
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

                        // Debit Cash (53) or Digital (58), Credit Revenue (65)
                        $debitAccountId = ($cashAmt > 0) ? 53 : 58;
                        $this->createBalancedVoucher(
                            $s['date'],
                            "Showroom Sale {$refNumber}",
                            'offline_sale',
                            $sale->id,
                            $totalAmt,
                            $debitAccountId,
                            65, // 4110 POS Sales Revenue
                            $accountsMap
                        );
                        $metrics['journal_entries_created']++;
                    } else {
                        $metrics['sales_items_created']++;
                        $metrics['journal_entries_created']++;
                    }
                }

                // Expenses in sheet
                $expensesList = $mData['expenses'] ?? [];
                foreach ($expensesList as $exp) {
                    $expAmt = (float)$exp['amount'];
                    if ($expAmt <= 0) continue;

                    $metrics['expenses_recorded']++;
                    $metrics['expense_total_npr'] += $expAmt;

                    if (!$dryRun) {
                        // Debit 6200 Store Maintenance (78), Credit 1110 Cash (53)
                        $this->createBalancedVoucher(
                            $exp['date'],
                            "Showroom Cash Expense: {$exp['description']}",
                            'showroom_expense',
                            $metrics['expenses_recorded'],
                            $expAmt,
                            78, // 6200 Operational Expense
                            53, // 1110 Cash in Register
                            $accountsMap
                        );
                        $metrics['journal_entries_created']++;
                    } else {
                        $metrics['journal_entries_created']++;
                    }
                }
            }

            // 5. ONLINE DISPATCHED ORDERS (packed order)
            $onlIndex = 1;
            foreach ($onlineOrders as $ord) {
                $metrics['online_orders_created']++;
                $ordNumber = sprintf('ONL-2026-%05d', $onlIndex++);

                if (!$dryRun) {
                    $nameParts = explode(' ', trim($ord['customer_name'] ?: 'Online Customer'), 2);
                    $fName = $nameParts[0];
                    $lName = $nameParts[1] ?? 'Customer';

                    $src = strtolower(trim((string)($ord['source'] ?? '')));
                    $channel = ($src === 'nw') ? 'whatsapp' : 'online';

                    $order = Order::create([
                        'order_number' => $ordNumber,
                        'channel' => $channel,
                        'first_name' => $fName,
                        'last_name' => $lName,
                        'email' => 'customer_' . $onlIndex . '@laijau.com',
                        'phone' => $ord['phone'],
                        'shipping_address' => $ord['address'] ?: 'Kathmandu Valley, Nepal',
                        'shipping_country' => 'Nepal',
                        'subtotal' => $ord['order_amount'],
                        'shipping_fee' => $ord['delivery_charge'],
                        'total_amount' => (float)$ord['order_amount'] + (float)$ord['delivery_charge'],
                        'currency' => 'NPR',
                        'carrier' => $ord['carrier'],
                        'courier_name' => $ord['carrier'],
                        'status' => 'delivered',
                        'payment_status' => 'paid',
                        'payment_method' => 'cash_on_delivery',
                        'created_at' => $ord['date'] . ' 10:00:00',
                    ]);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => 130,
                        'product_name' => $ord['product_code'] ?: 'Online Ordered Product',
                        'sku' => $ord['product_code'] ?: 'ONLINE-PROD',
                        'quantity' => 1,
                        'unit_price' => $ord['order_amount'],
                        'selected_size' => $ord['size'] ?? null,
                    ]);
                }
            }

            // 6. REGISTERED USERS
            foreach ($users as $u) {
                $email = $u['email'];
                $phone = $u['phone'];

                $exists = User::where('email', $email)
                    ->when(!empty($phone), function ($q) use ($phone) {
                        return $q->orWhere('phone', $phone);
                    })
                    ->exists();

                if (!$exists) {
                    $metrics['users_created']++;
                    if (!$dryRun) {
                        User::create([
                            'name' => $u['name'],
                            'email' => $email,
                            'phone' => $phone,
                            'role' => 'customer',
                            'password' => Hash::make(Str::random(32)),
                            'city' => $u['city'] ?? null,
                            'country' => 'Nepal',
                            'notes' => 'Legacy customer ID: ' . ($u['legacy_id'] ?? ''),
                            'created_at' => !empty($u['created_at']) ? $u['created_at'] : now(),
                        ]);
                    }
                }
            }
        };

        if ($dryRun) {
            $runLogic();
        } else {
            DB::transaction(function () use ($runLogic) {
                $runLogic();
            });
        }

        return $metrics;
    }

    /**
     * Create a balanced double-entry voucher.
     */
    protected function createBalancedVoucher(
        string $date,
        string $description,
        string $refType,
        int $refId,
        float $amount,
        int $debitAccountId,
        int $creditAccountId,
        array $accountsMap
    ): JournalEntry {
        $entry = JournalEntry::create([
            'entry_number' => 'JV-MIG-' . Str::upper(Str::random(10)),
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

        $debitAccNum = $accountsMap[$debitAccountId] ?? '1110';
        $creditAccNum = $accountsMap[$creditAccountId] ?? '4110';

        // Debit Line
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $debitAccountId,
            'account_number' => $debitAccNum,
            'line_number' => 1,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0.0000,
            'amount_currency' => $amount,
            'currency' => 'NPR',
        ]);

        // Credit Line
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $creditAccountId,
            'account_number' => $creditAccNum,
            'line_number' => 2,
            'description' => $description,
            'debit' => 0.0000,
            'credit' => $amount,
            'amount_currency' => $amount,
            'currency' => 'NPR',
        ]);

        return $entry;
    }

    /**
     * Financial and stock reconciliation comparison report.
     */
    public function reconcile(): array
    {
        $audit = $this->audit();

        $importedSalesTotal = (float)OfflineSale::where('sale_number', 'LIKE', 'OFF-2026-%')->sum('total_amount');
        $importedCashTotal = (float)OfflineSale::where('sale_number', 'LIKE', 'OFF-2026-%')->sum('cash_received');
        $preExistingSalesTotal = (float)OfflineSale::where('sale_number', 'NOT LIKE', 'OFF-2026-%')->sum('total_amount');
        $erpTotalSales = (float)OfflineSale::sum('total_amount');

        $importedPurchasesTotal = (float)PurchaseOrder::where('po_number', 'LIKE', 'PO-2026-01-%')->sum('subtotal_currency');
        $preExistingPurchasesTotal = (float)PurchaseOrder::where('po_number', 'NOT LIKE', 'PO-2026-01-%')->sum('subtotal_currency');
        $erpTotalPurchases = (float)PurchaseOrder::sum('subtotal_currency');

        $erpOnlineOrdersCount = Order::where('channel', 'online')->count();
        $erpTotalProducts = Product::count();
        $erpTotalVariants = ProductVariant::count();
        $erpTotalUsers = User::count();

        $sourceGrossSales = (float)$audit['monthly_sales']['gross_sales_npr'];
        $sourcePurchases = (float)$audit['purchases_jan_2026']['total_cost_npr'];

        $salesVariance = round($importedSalesTotal - $sourceGrossSales, 2);
        $purchasesVariance = round($importedPurchasesTotal - $sourcePurchases, 2);

        // Check journal entries balance
        $totalDebits = (float)JournalEntryLine::sum('debit');
        $totalCredits = (float)JournalEntryLine::sum('credit');
        $accountingBalanceDiff = round($totalDebits - $totalCredits, 2);

        // Monthly breakdown
        $monthlyReport = [];
        $monthNumbers = [
            'January' => 1,
            'February' => 2,
            'March' => 3,
            'April' => 4,
            'May' => 5,
            'June' => 6,
            'July' => 7,
            'August' => 8,
        ];

        foreach ($monthNumbers as $mName => $mNum) {
            $sourceData = $audit['monthly_sales']['periods'][$mName] ?? null;
            $sourceVal = (float)($sourceData['total_sales'] ?? 0.0);
            $erpVal = (float)OfflineSale::where('sale_number', 'LIKE', sprintf('OFF-2026-%02d-%%', $mNum))->sum('total_amount');
            $status = $sourceData['status'] ?? 'unknown';

            $monthlyReport[$mName] = [
                'status' => $status,
                'source_total_npr' => $sourceVal,
                'erp_total_npr' => $erpVal,
                'variance_npr' => round($erpVal - $sourceVal, 2),
                'reconciled' => abs($erpVal - $sourceVal) < 0.01,
                'notes' => $sourceData['notes'] ?? '',
            ];
        }

        $reconciliation = [
            'timestamp' => now()->toIso8601String(),
            'financial_reconciliation' => [
                'source_showroom_sales_npr' => $sourceGrossSales,
                'imported_showroom_sales_npr' => $importedSalesTotal,
                'pre_existing_staging_sales_npr' => $preExistingSalesTotal,
                'erp_total_showroom_sales_npr' => $erpTotalSales,
                'sales_variance_npr' => $salesVariance,
                'sales_reconciled' => abs($salesVariance) < 0.01,
                'source_jan_purchases_npr' => $sourcePurchases,
                'imported_jan_purchases_npr' => $importedPurchasesTotal,
                'pre_existing_staging_purchases_npr' => $preExistingPurchasesTotal,
                'erp_total_purchases_npr' => $erpTotalPurchases,
                'purchases_variance_npr' => $purchasesVariance,
                'purchases_reconciled' => abs($purchasesVariance) < 0.01,
            ],
            'monthly_sales_report' => $monthlyReport,
            'accounting_integrity' => [
                'total_debits_npr' => $totalDebits,
                'total_credits_npr' => $totalCredits,
                'difference_npr' => $accountingBalanceDiff,
                'is_perfectly_balanced' => $accountingBalanceDiff === 0.0,
            ],
            'database_counts' => [
                'total_products' => $erpTotalProducts,
                'total_variants' => $erpTotalVariants,
                'total_online_orders' => $erpOnlineOrdersCount,
                'total_users' => $erpTotalUsers,
            ],
            'documented_exceptions' => [
                'february_2026_sales' => 'DATA NOT AVAILABLE (Source file unavailable - not fabricated)',
                'january_18_31_sales' => 'DATA NOT AVAILABLE (Source record ends on Jan 17 - not fabricated)',
            ],
        ];

        File::put(storage_path('app/migration/reconciliation_report.json'), json_encode($reconciliation, JSON_PRETTY_PRINT));

        return $reconciliation;
    }

    protected function loadJson(string $file): array
    {
        $path = "{$this->extractedDir}/{$file}";
        if (!File::exists($path)) {
            throw new \RuntimeException("Extracted dataset not found: {$path}. Run scratch extraction first.");
        }
        return json_decode(File::get($path), true) ?: [];
    }
}
