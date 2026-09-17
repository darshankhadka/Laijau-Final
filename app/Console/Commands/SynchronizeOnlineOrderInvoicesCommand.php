<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounting\AccountingFiscalYear;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SynchronizeOnlineOrderInvoicesCommand extends Command
{
    protected $signature = 'laijau:sync-order-invoices {--live : Execute database update}';
    protected $description = 'Synchronize all online sales invoices and journal entries with their authentic order dates and customer names from NCM';

    public function handle(): int
    {
        $live = (bool) $this->option('live');

        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — SYNCHRONIZE ONLINE SALES INVOICES & GL DATES');
        $this->info('  Mode: ' . ($live ? '<error>LIVE EXECUTION</error>' : '<comment>DRY-RUN (SIMULATION)</comment>'));
        $this->info('========================================================================');

        // 1. Check orphaned OFF-000019
        $orphanInv = DB::table('accounting_invoices')->where('invoice_number', 'OFF-000019')->first();
        if ($orphanInv) {
            $this->warn("Found orphaned legacy mock invoice OFF-000019 (ID: {$orphanInv->id}) on 2026-09-12.");
        }

        // 2. Fetch all authentic online orders
        $orders = Order::where('order_number', 'LIKE', 'ONL-2026-%')->get();
        $this->info("\nFound {$orders->count()} authentic online orders from NCM Real Data.");

        $dateDistribution = [];
        $samplNppdates = [];

        foreach ($orders as $idx => $order) {
            $orderDate = substr((string)$order->created_at, 0, 10);
            $ym = substr($orderDate, 0, 7);
            $dateDistribution[$ym] = ($dateDistribution[$ym] ?? 0) + 1;

            if ($idx < 5) {
                $custName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
                $samplNppdates[] = [
                    $order->order_number,
                    $custName ?: 'N/A',
                    $order->phone ?: 'N/A',
                    $orderDate,
                    $order->delivered_at ? substr((string)$order->delivered_at, 0, 10) : $orderDate,
                ];
            }
        }

        ksort($dateDistribution);
        $this->info("\n--- Authentic Monthly Order Distribution ---");
        $distRows = [];
        foreach ($dateDistribution as $ym => $cnt) {
            $distRows[] = [$ym, number_format($cnt)];
        }
        $this->table(['Year-Month', 'Orders Count'], $distRows);

        $this->info("\n--- Sample Authentic Updates to Bikri Khata ---");
        $this->table(['Order #', 'Customer Name', 'Phone', 'Order Date (Miti)', 'Delivered Date'], $samplNppdates);

        if (!$live) {
            $this->warn("\nRun with --live to synchronize all {$orders->count()} invoices and journal entries.");
            return 0;
        }

        $this->info("\n--- EXECUTING SYNCHRONIZATION IN TRANSACTION ---");

        DB::beginTransaction();
        try {
            // A. Purge orphan OFF-000019 invoice and JE if present
            if ($orphanInv) {
                if ($orphanInv->journal_entry_id) {
                    DB::table('accounting_journal_entry_lines')->where('journal_entry_id', $orphanInv->journal_entry_id)->delete();
                    DB::table('accounting_journal_entries')->where('id', $orphanInv->journal_entry_id)->delete();
                }
                DB::table('accounting_invoice_items')->where('accounting_invoice_id', $orphanInv->id)->delete();
                DB::table('accounting_invoices')->where('id', $orphanInv->id)->delete();
                $this->info("✓ Purged orphaned legacy mock invoice OFF-000019 and its GL entry.");
            }

            // B. Synchronize each online order's invoice and journal entry
            $updatedInvoices = 0;
            $updatedJes = 0;

            foreach ($orders as $order) {
                $orderDate = substr((string)$order->created_at, 0, 10);
                $dueDate = $order->delivered_at ? substr((string)$order->delivered_at, 0, 10) : $orderDate;
                $custName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
                if (empty($custName)) {
                    $custName = 'Online Customer';
                }

                $fiscalYear = ($orderDate < '2026-07-16') ? '2082/83' : '2083/84';

                // Update AccountingInvoice
                $invUpdated = DB::table('accounting_invoices')
                    ->where('reference_order_id', $order->id)
                    ->update([
                        'issue_date' => $orderDate,
                        'due_date' => $dueDate,
                        'contact_name' => $custName,
                        'contact_phone' => $order->phone,
                        'contact_address' => $order->shipping_address,
                        'fiscal_year' => $fiscalYear,
                    ]);

                if ($invUpdated > 0) {
                    $updatedInvoices++;
                }

                $invoiceNumber = DB::table('accounting_invoices')
                    ->where('reference_order_id', $order->id)
                    ->value('invoice_number');

                // Update Accounting Journal Entry
                $jeUpdated = DB::table('accounting_journal_entries')
                    ->where('source_type', 'online_order')
                    ->where('source_id', $order->id)
                    ->whereNull('deleted_at')
                    ->update([
                        'voucher_date' => $orderDate,
                        'reference_number' => $invoiceNumber,
                        'description' => "Sales revenue recognized for Invoice {$invoiceNumber} (Order {$order->order_number})",
                        'updated_at' => now(),
                    ]);

                if ($jeUpdated > 0) {
                    $updatedJes++;
                }
            }

            $this->info("✓ Synchronized {$updatedInvoices} statutory invoices with authentic dates and customer names.");
            $this->info("✓ Synchronized {$updatedJes} journal entries with authentic voucher dates.");

            // C. Invariant Verification
            $stock = (int) DB::table('inventory_stock_levels')->sum('quantity_on_hand');
            $dueBalance = (float) DB::table('inventory_suppliers')->sum('due_balance');
            $glDebit = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
            $glCredit = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
            $glVariance = abs($glDebit - $glCredit);

            $this->info("\n--- POST-SYNC INVARIANT VERIFICATION ---");
            $this->info("Physical Stock:          {$stock} pcs (Target: 2,721 pcs)");
            $this->info("Supplier Due Balance:    Rs. " . number_format($dueBalance, 2) . " (Target: Rs. 4,212,905.00)");
            $this->info("General Ledger Debit:    NPR " . number_format($glDebit, 2));
            $this->info("General Ledger Credit:   NPR " . number_format($glCredit, 2));
            $this->info("General Ledger Variance: NPR " . number_format($glVariance, 4) . " (Target: 0.0000)");

            if ($stock !== 2721) {
                throw new \RuntimeException("INVARIANT VIOLATION: Physical stock altered!");
            }
            if (abs($dueBalance - 4212905.00) > 0.01) {
                throw new \RuntimeException("INVARIANT VIOLATION: Supplier due balance altered!");
            }
            if ($glVariance > 0.0001) {
                throw new \RuntimeException("INVARIANT VIOLATION: General ledger unbalanced! Variance: {$glVariance}");
            }

            DB::commit();
            $this->info("\n✓ ALL ONLINE SALES INVOICES & GL ENTRIES SYNCHRONIZED SUCCESSFULLY!");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("SYNC FAILED: " . $e->getMessage());
            return 1;
        }
    }
}
