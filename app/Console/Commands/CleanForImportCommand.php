<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanForImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'laijau:clean-for-import
                            {--force : Force execution without confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Clean all mock, test, and transient transactional records preparing the database for real backdate data import';

    public function handle(): int
    {
        $this->info('========================================================================');
        $this->info('  LAIJAU ERP — CLEAN-ROOM DATABASE PURGE FOR REAL DATA IMPORT');
        $this->info('========================================================================');

        if (!$this->option('force') && !$this->confirm('Are you sure you want to clean all mock/transactional data for real data import?')) {
            $this->warn('Operation cancelled by operator.');
            return self::SUCCESS;
        }

        $this->line('Starting clean-room reset...');

        // Disable foreign key checks for safe truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tablesToTruncate = [
            // Catalog & Mock Products
            'products',
            'product_variants',
            'product_attributes',
            'category_product',
            'collections',
            'collection_product',
            'price_histories',
            'sku_sequences',

            // Inventory & Logistics Operational Records
            'inventory_stock_levels',
            'inventory_stock_counts',
            'inventory_stock_count_items',
            'inventory_transfers',
            'inventory_transfer_items',
            'inventory_stock_movements',
            'inventory_reservations',
            'inventory_adjustments',
            'inventory_suppliers',
            'inventory_purchase_orders',
            'inventory_purchase_order_items',
            'restock_requests',

            // Orders, Sales, and Shipments
            'orders',
            'order_items',
            'shipments',
            'logistics_events',
            'offline_sales',
            'offline_sale_items',
            'offline_sale_void_logs',
            'coupons',
            'payment_audit_logs',

            // Accounting & General Ledger
            'accounting_invoices',
            'accounting_invoice_items',
            'accounting_journal_entries',
            'accounting_journal_entry_lines',
            'accounting_bank_transactions',
            'accounting_cod_settlements',
            'accounting_tds_records',
            'accounting_vat_declarations',

            // CRM & Customer Interactions
            'crm_leads',
            'crm_activities',
            'contact_messages',

            // HRM Operational Records (Preserving departments, positions, tax configs)
            'hrm_employees',
            'hrm_employment_contracts',
            'hrm_expense_claims',
            'hrm_holiday_balances',
            'hrm_leave_requests',
            'hrm_payroll_runs',
            'hrm_payroll_run_items',
            'hrm_timesheets',
            'hrm_recruitment_applicants',
            'hrm_recruitment_jobs',
            'hrm_performance_reviews',
            'hrm_employee_documents',

            // Sequences & Queue Logs
            'document_sequences',
            'failed_jobs',
            'jobs',
            'job_batches',
            'setting_audit_logs',
            'module_setting_audit_logs',
            'personal_access_tokens',
            'password_reset_tokens',
        ];

        $truncatedCount = 0;
        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $truncatedCount++;
                $this->line("  ✓ Truncated {$table}");
            }
        }

        // Purge non-admin test users, preserving super admin
        if (Schema::hasTable('users')) {
            $deletedUsers = DB::table('users')
                ->where('role', '!=', 'admin')
                ->where('email', '!=', 'admin@laijau.com')
                ->delete();
            if ($deletedUsers > 0) {
                $this->line("  ✓ Deleted {$deletedUsers} non-admin user(s)");
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info("✓ Clean-room reset completed successfully! {$truncatedCount} table(s) truncated.");
        $this->line('All sequences reset to 1. All master configurations and admin accounts preserved.');
        $this->info('========================================================================');

        return self::SUCCESS;
    }
}
