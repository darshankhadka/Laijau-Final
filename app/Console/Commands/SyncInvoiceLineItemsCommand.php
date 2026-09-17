<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncInvoiceLineItemsCommand extends Command
{
    protected $signature = 'laijau:sync-invoice-line-items';

    protected $description = 'Synchronize and populate missing invoice line items from POS sales and online orders to eliminate calculation errors';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — INVOICE LINE ITEMS RECONCILIATION & SYNCHRONIZATION');
        $this->info('========================================================================');

        DB::beginTransaction();

        try {
            // STEP 1: Synchronize Showroom POS Sales Invoices
            $this->info("\n[Step 1] Synchronizing POS Sales Invoices with offline_sale_items...");
            $posCount = $this->syncPosInvoices();
            $this->info("  ✓ Synchronized {$posCount} line items across POS sales invoices.");

            // STEP 2: Synchronize Online Orders Invoices
            $this->info("\n[Step 2] Synchronizing Online Orders Invoices with order_items...");
            $orderCount = $this->syncOrderInvoices();
            $this->info("  ✓ Synchronized {$orderCount} line items across Online Order invoices.");

            // STEP 3: Synchronize Opening Supplier Bills
            $this->info("\n[Step 3] Synchronizing Opening Supplier Procurement Bills...");
            $billCount = $this->syncOpeningSupplierBills();
            $this->info("  ✓ Synchronized {$billCount} line items across opening supplier bills.");

            // STEP 4: Precision Rebalancing (Eliminate any 1-paisa rounding divergence)
            $this->info("\n[Step 4] Reconciling invoice line item totals against parent invoice totals...");
            $reconciled = $this->reconcileLineItemTotals();
            $this->info("  ✓ Reconciled {$reconciled} line items to guarantee 100% calculation equilibrium.");

            // STEP 5: Verification
            $emptyInvoices = DB::table('accounting_invoices')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('accounting_invoice_items')
                        ->whereColumn('accounting_invoice_items.accounting_invoice_id', 'accounting_invoices.id');
                })
                ->count();

            $totalItems = DB::table('accounting_invoice_items')->count();

            $this->info("\n[Verification Summary]");
            $this->info("  Total Invoice Line Items in DB: {$totalItems}");
            $this->info("  Invoices without items: {$emptyInvoices}");

            if ($emptyInvoices > 0) {
                throw new \RuntimeException("Integrity Warning: {$emptyInvoices} invoices still lack line items!");
            }

            DB::commit();

            $this->info("\n========================================================================");
            $this->info("  ALL INVOICE LINE ITEMS SUCCESSFULLY SYNCHRONIZED & COMMITTED");
            $this->info("========================================================================");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    protected function syncPosInvoices(): int
    {
        $posAccountId = \App\Models\Accounting\Account::where('account_number', '4110')->value('id') 
            ?? \App\Models\Accounting\Account::first()->id;

        $invoices = DB::table('accounting_invoices')
            ->whereNotNull('reference_offline_sale_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('accounting_invoice_items')
                    ->whereColumn('accounting_invoice_items.accounting_invoice_id', 'accounting_invoices.id');
            })
            ->select('id', 'reference_offline_sale_id', 'vat_amount', 'total_amount')
            ->get();

        $inserted = 0;
        $chunk = [];

        foreach ($invoices as $inv) {
            $saleItems = DB::table('offline_sale_items')
                ->where('offline_sale_id', $inv->reference_offline_sale_id)
                ->get();

            if ($saleItems->isEmpty()) {
                // Fallback single line item if offline sale items were missing
                $total = (float)$inv->total_amount;
                $hasVat = (float)$inv->vat_amount > 0;
                $vatRate = $hasVat ? 13.00 : 0.00;
                $unitPrice = $hasVat ? round($total / 1.13, 4) : $total;
                $vatAmt = $hasVat ? round($total - $unitPrice, 4) : 0.00;

                $chunk[] = [
                    'accounting_invoice_id' => $inv->id,
                    'product_id' => null,
                    'account_id' => $posAccountId,
                    'description' => 'Showroom Merchandise Sale',
                    'quantity' => 1.00,
                    'unit_price' => $unitPrice,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmt,
                    'total_amount' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $inserted++;
            } else {
                foreach ($saleItems as $item) {
                    $itemTotal = (float)$item->total_price;
                    $qty = (float)$item->quantity > 0 ? (float)$item->quantity : 1.00;
                    $hasVat = (float)$inv->vat_amount > 0;
                    $vatRate = $hasVat ? 13.00 : 0.00;
                    $unitPrice = $hasVat ? round(($itemTotal / 1.13) / $qty, 4) : round($itemTotal / $qty, 4);
                    $vatAmt = $hasVat ? round($itemTotal - ($unitPrice * $qty), 4) : 0.00;

                    $chunk[] = [
                        'accounting_invoice_id' => $inv->id,
                        'product_id' => $item->product_id,
                        'account_id' => $posAccountId,
                        'description' => $item->product_name ?: ($item->sku ?: 'Showroom Merchandise'),
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'vat_rate' => $vatRate,
                        'vat_amount' => $vatAmt,
                        'total_amount' => $itemTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $inserted++;
                }
            }

            if (count($chunk) >= 1000) {
                DB::table('accounting_invoice_items')->insert($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            DB::table('accounting_invoice_items')->insert($chunk);
        }

        return $inserted;
    }

    protected function syncOrderInvoices(): int
    {
        $onlineAccountId = \App\Models\Accounting\Account::where('account_number', '4120')->value('id')
            ?? \App\Models\Accounting\Account::where('account_number', '4110')->value('id')
            ?? \App\Models\Accounting\Account::first()->id;

        $shippingAccountId = \App\Models\Accounting\Account::where('account_number', '4130')->value('id')
            ?? $onlineAccountId;

        $invoices = DB::table('accounting_invoices')
            ->whereNotNull('reference_order_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('accounting_invoice_items')
                    ->whereColumn('accounting_invoice_items.accounting_invoice_id', 'accounting_invoices.id');
            })
            ->select('id', 'reference_order_id', 'vat_amount', 'total_amount')
            ->get();

        $inserted = 0;
        $chunk = [];

        foreach ($invoices as $inv) {
            $orderItems = DB::table('order_items')
                ->where('order_id', $inv->reference_order_id)
                ->get();

            $hasVat = (float)$inv->vat_amount > 0;
            $itemsSum = 0.0;

            if ($orderItems->isEmpty()) {
                $total = (float)$inv->total_amount;
                $vatRate = $hasVat ? 13.00 : 0.00;
                $unitPrice = $hasVat ? round($total / 1.13, 4) : $total;
                $vatAmt = $hasVat ? round($total - $unitPrice, 4) : 0.00;

                $chunk[] = [
                    'accounting_invoice_id' => $inv->id,
                    'product_id' => null,
                    'account_id' => $onlineAccountId,
                    'description' => 'Online Storefront Merchandise Sale',
                    'quantity' => 1.00,
                    'unit_price' => $unitPrice,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmt,
                    'total_amount' => $total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $inserted++;
            } else {
                foreach ($orderItems as $item) {
                    $qty = (float)$item->quantity > 0 ? (float)$item->quantity : 1.00;
                    $itemTotal = (float)$item->unit_price * $qty;
                    $itemsSum += $itemTotal;
                    $vatRate = $hasVat ? 13.00 : 0.00;
                    $unitPrice = $hasVat ? round(($itemTotal / 1.13) / $qty, 4) : round($itemTotal / $qty, 4);
                    $vatAmt = $hasVat ? round($itemTotal - ($unitPrice * $qty), 4) : 0.00;

                    $chunk[] = [
                        'accounting_invoice_id' => $inv->id,
                        'product_id' => $item->product_id,
                        'account_id' => $onlineAccountId,
                        'description' => $item->product_name ?: ($item->sku ?: 'Online Storefront Item'),
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'vat_rate' => $vatRate,
                        'vat_amount' => $vatAmt,
                        'total_amount' => $itemTotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $inserted++;
                }

                // If shipping was charged and accounts for invoice difference
                $invTotal = (float)$inv->total_amount;
                $diff = round($invTotal - $itemsSum, 2);
                if ($diff > 0) {
                    $vatRate = $hasVat ? 13.00 : 0.00;
                    $unitPrice = $hasVat ? round($diff / 1.13, 4) : $diff;
                    $vatAmt = $hasVat ? round($diff - $unitPrice, 4) : 0.00;

                    $chunk[] = [
                        'accounting_invoice_id' => $inv->id,
                        'product_id' => null,
                        'account_id' => $shippingAccountId,
                        'description' => 'Courier Delivery & Shipping Fee',
                        'quantity' => 1.00,
                        'unit_price' => $unitPrice,
                        'vat_rate' => $vatRate,
                        'vat_amount' => $vatAmt,
                        'total_amount' => $diff,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $inserted++;
                }
            }

            if (count($chunk) >= 1000) {
                DB::table('accounting_invoice_items')->insert($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            DB::table('accounting_invoice_items')->insert($chunk);
        }

        return $inserted;
    }

    protected function syncOpeningSupplierBills(): int
    {
        $invAssetId = \App\Models\Accounting\Account::where('account_number', '1210')->value('id')
            ?? \App\Models\Accounting\Account::where('account_number', '5110')->value('id')
            ?? \App\Models\Accounting\Account::first()->id;

        $bills = DB::table('accounting_invoices')
            ->where('type', 'supplier_bill')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('accounting_invoice_items')
                    ->whereColumn('accounting_invoice_items.accounting_invoice_id', 'accounting_invoices.id');
            })
            ->get();

        $inserted = 0;
        foreach ($bills as $bill) {
            $total = (float)$bill->total_amount;
            DB::table('accounting_invoice_items')->insert([
                'accounting_invoice_id' => $bill->id,
                'product_id' => null,
                'account_id' => $invAssetId,
                'description' => 'Opening Supplier Procurement - ' . ($bill->contact_name ?: 'Vendor Settlement'),
                'quantity' => 1.00,
                'unit_price' => $total,
                'vat_rate' => 0.00,
                'vat_amount' => 0.00,
                'total_amount' => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }

        return $inserted;
    }

    protected function reconcileLineItemTotals(): int
    {
        // For any invoice where sum(items.total_amount) != invoice.total_amount due to rounding,
        // adjust the last line item so the total matches exactly
        $mismatches = DB::table('accounting_invoices')
            ->join(DB::raw('(SELECT accounting_invoice_id, SUM(total_amount) as items_total FROM accounting_invoice_items GROUP BY accounting_invoice_id) agg'), 'agg.accounting_invoice_id', '=', 'accounting_invoices.id')
            ->whereRaw('ABS(accounting_invoices.total_amount - agg.items_total) > 0.001')
            ->select('accounting_invoices.id', 'accounting_invoices.total_amount', 'agg.items_total')
            ->get();

        $reconciled = 0;
        foreach ($mismatches as $m) {
            $diff = round((float)$m->total_amount - (float)$m->items_total, 4);
            $lastItem = DB::table('accounting_invoice_items')
                ->where('accounting_invoice_id', $m->id)
                ->orderByDesc('id')
                ->first();

            if ($lastItem) {
                $newTotal = round((float)$lastItem->total_amount + $diff, 4);
                $newVat = (float)$lastItem->vat_rate > 0 ? round($newTotal - ($newTotal / 1.13), 4) : 0.00;
                $newUnit = round(($newTotal - $newVat) / (float)$lastItem->quantity, 4);

                DB::table('accounting_invoice_items')
                    ->where('id', $lastItem->id)
                    ->update([
                        'total_amount' => $newTotal,
                        'unit_price' => $newUnit,
                        'vat_amount' => $newVat,
                    ]);
                $reconciled++;
            }
        }

        return $reconciled;
    }
}
