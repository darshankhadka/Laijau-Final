<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeTestContaminationCommand extends Command
{
    protected $signature = 'laijau:purge-test-contamination {--live : Execute destructive deletion}';
    protected $description = 'Forensic purge of test/demo products, mock test orders, test invoices, and test GL entries';

    public function handle(): int
    {
        $live = (bool) $this->option('live');

        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — TEST & DEMO CONTAMINATION FORENSIC PURGE');
        $this->info('  Mode: ' . ($live ? '<error>LIVE EXECUTION</error>' : '<comment>DRY-RUN (SIMULATION)</comment>'));
        $this->info('========================================================================');

        // 1. Identify Test Products
        $testProductIds = [654, 655, 656, 657, 658, 659, 660, 661, 1053, 1054, 1055, 1056, 1057, 1058, 1059, 1060];
        $testProducts = DB::table('products')->whereIn('id', $testProductIds)->get(['id', 'name', 'sku', 'is_published']);

        $this->info("\n--- 1. TEST PRODUCTS TO PURGE (" . $testProducts->count() . " found) ---");
        foreach ($testProducts as $p) {
            $this->line(" [ID: {$p->id}] SKU: {$p->sku} | Name: {$p->name} (Published: {$p->is_published})");
        }

        // 2. Identify Test Orders
        $testOrders = DB::table('orders')
            ->where(function ($q) {
                $q->where('order_number', 'LIKE', 'LJ-TEST-%')
                  ->orWhere('order_number', 'LIKE', 'ORD-TEST%')
                  ->orWhere('order_number', 'LIKE', 'ORD-SEC-%')
                  ->orWhere('order_number', 'LIKE', 'LJ-00%')
                  ->orWhere('order_number', 'LIKE', 'LJ-01%')
                  ->orWhere('order_number', 'LIKE', 'NA-%')
                  ->orWhere('email', 'LIKE', '%@example.com')
                  ->orWhere('email', 'LIKE', 'guest_LJ-%');
            })
            ->where('order_number', 'NOT LIKE', 'ONL-2026-%')
            ->where('order_number', 'NOT LIKE', 'CAN-2026-%')
            ->get(['id', 'order_number', 'email', 'phone', 'total_amount', 'user_id']);

        $testOrderIds = $testOrders->pluck('id')->all();
        $this->info("\n--- 2. TEST ORDERS TO PURGE (" . count($testOrderIds) . " found) ---");

        // 3. Relational artifacts
        $testOrderItemsCount = DB::table('order_items')->whereIn('order_id', $testOrderIds)->count();
        $testInvoices = DB::table('accounting_invoices')->whereIn('reference_order_id', $testOrderIds)->get(['id', 'invoice_number', 'journal_entry_id']);
        $testInvoiceIds = $testInvoices->pluck('id')->all();

        $testJeIds = DB::table('accounting_journal_entries')
            ->where('reference_type', 'order')
            ->whereIn('reference_id', $testOrderIds)
            ->pluck('id')
            ->all();

        $testJeLinesCount = DB::table('accounting_journal_entry_lines')->whereIn('journal_entry_id', $testJeIds)->count();
        $testAuditLogsCount = DB::table('payment_audit_logs')->whereIn('order_id', $testOrderIds)->count();
        $testShipmentsCount = DB::table('shipments')->whereIn('order_id', $testOrderIds)->count();

        // 4. Test users
        $testUserIds = [327, 328, 329, 330, 331, 332, 333];
        $testUsers = DB::table('users')->whereIn('id', $testUserIds)->get(['id', 'name', 'email']);

        // 5. Offline sales remap check
        $remappingItems = DB::table('offline_sale_items')->whereIn('product_id', $testProductIds)->get();

        $this->table(['Artifact Type', 'Count', 'Action'], [
            ['Test Products', $testProducts->count(), 'Delete from products'],
            ['Test Orders', count($testOrderIds), 'Delete from orders'],
            ['Test Order Items', $testOrderItemsCount, 'Delete from order_items'],
            ['Test Statutory Invoices', count($testInvoiceIds), 'Delete from accounting_invoices'],
            ['Test Journal Entries', count($testJeIds), 'Delete from accounting_journal_entries'],
            ['Test Journal Entry Lines', $testJeLinesCount, 'Delete from accounting_journal_entry_lines'],
            ['Test Payment Audit Logs', $testAuditLogsCount, 'Delete from payment_audit_logs'],
            ['Shipments Linked to Test Orders', $testShipmentsCount, 'Unlink (set order_id=null, match_status=unmatched)'],
            ['Test / Demo Users', $testUsers->count(), 'Delete from users'],
            ['POS Sales Items Referencing Test IDs', $remappingItems->count(), 'Remap product_id => 130 (Sneakers-06)'],
        ]);

        if (!$live) {
            $this->warn("\nRun with --live to execute this purge inside a safe database transaction.");
            return 0;
        }

        $this->info("\n--- EXECUTING PURGE IN DATABASE TRANSACTION ---");

        DB::beginTransaction();
        try {
            // A. Remap offline sales items
            if ($remappingItems->isNotEmpty()) {
                DB::table('offline_sale_items')->whereIn('product_id', $testProductIds)->update([
                    'product_id' => 130,
                    'sku' => '153065',
                ]);
                $this->info("✓ Remapped {$remappingItems->count()} offline sale items to canonical showroom product #130.");
            }

            // B. Unlink shipments
            if ($testShipmentsCount > 0) {
                DB::table('shipments')->whereIn('order_id', $testOrderIds)->update([
                    'order_id' => null,
                    'match_status' => 'unmatched',
                    'match_method' => null,
                    'match_confidence' => null,
                    'match_reason' => 'Unlinked during test contamination purge',
                ]);
                $this->info("✓ Unlinked {$testShipmentsCount} shipment(s) from test orders.");
            }

            // C. Delete test audit logs
            if ($testAuditLogsCount > 0) {
                DB::table('payment_audit_logs')->whereIn('order_id', $testOrderIds)->delete();
                $this->info("✓ Deleted {$testAuditLogsCount} test payment audit logs.");
            }

            // D. Delete test journal entry lines & entries
            if ($testJeLinesCount > 0) {
                DB::table('accounting_journal_entry_lines')->whereIn('journal_entry_id', $testJeIds)->delete();
                DB::table('accounting_journal_entries')->whereIn('id', $testJeIds)->delete();
                $this->info("✓ Deleted " . count($testJeIds) . " test journal entries and {$testJeLinesCount} lines.");
            }

            // E. Delete test invoices
            if (count($testInvoiceIds) > 0) {
                DB::table('accounting_invoice_items')->whereIn('accounting_invoice_id', $testInvoiceIds)->delete();
                DB::table('accounting_invoices')->whereIn('id', $testInvoiceIds)->delete();
                $this->info("✓ Deleted " . count($testInvoiceIds) . " test statutory invoices.");
            }

            // F. Delete test order items & orders
            if ($testOrderItemsCount > 0) {
                DB::table('order_items')->whereIn('order_id', $testOrderIds)->delete();
            }
            DB::table('orders')->whereIn('id', $testOrderIds)->delete();
            $this->info("✓ Deleted " . count($testOrderIds) . " test orders and {$testOrderItemsCount} order items.");

            // G. Delete test users
            DB::table('users')->whereIn('id', $testUserIds)->delete();
            $this->info("✓ Deleted {$testUsers->count()} test users.");

            // H. Delete test products
            DB::table('category_product')->whereIn('product_id', $testProductIds)->delete();
            DB::table('products')->whereIn('id', $testProductIds)->delete();
            $this->info("✓ Deleted {$testProducts->count()} test products.");

            // I. Verify Invariants
            $stock = (int) DB::table('inventory_stock_levels')->sum('quantity_on_hand');
            $dueBalance = (float) DB::table('inventory_suppliers')->sum('due_balance');
            $glDebit = (float) DB::table('accounting_journal_entry_lines')->sum('debit');
            $glCredit = (float) DB::table('accounting_journal_entry_lines')->sum('credit');
            $glVariance = abs($glDebit - $glCredit);
            $publishedProducts = DB::table('products')->where('is_published', 1)->count();
            $remainingTestProducts = DB::table('products')->whereIn('id', $testProductIds)->count();

            $this->info("\n--- POST-PURGE STATUTORY INVARIANT VERIFICATION ---");
            $this->info("Physical Stock:          {$stock} pcs (Target: 2,721 pcs)");
            $this->info("Supplier Due Balance:    Rs. " . number_format($dueBalance, 2) . " (Target: Rs. 4,212,905.00)");
            $this->info("General Ledger Variance: NPR " . number_format($glVariance, 4) . " (Target: 0.0000)");
            $this->info("Storefront Published:    {$publishedProducts} (Target: >= 500)");
            $this->info("Remaining Test Products: {$remainingTestProducts} (Target: 0)");

            if ($stock !== 2721) {
                throw new \RuntimeException("INVARIANT VIOLATION: Physical stock altered to {$stock}!");
            }
            if (abs($dueBalance - 4212905.00) > 0.01) {
                throw new \RuntimeException("INVARIANT VIOLATION: Supplier due balance altered to {$dueBalance}!");
            }
            if ($glVariance > 0.0001) {
                throw new \RuntimeException("INVARIANT VIOLATION: General ledger unbalanced! Variance: {$glVariance}");
            }
            if ($publishedProducts < 500) {
                throw new \RuntimeException("INVARIANT VIOLATION: Published storefront catalog compromised!");
            }
            if ($remainingTestProducts > 0) {
                throw new \RuntimeException("INVARIANT VIOLATION: Test products still present!");
            }

            DB::commit();
            $this->info("\n✓ TRANSACTION COMMITTED SUCCESSFULLY! All invariants fully preserved.");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("PURGE FAILED: " . $e->getMessage());
            return 1;
        }
    }
}
