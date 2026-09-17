<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\RealDataMigrationService;
use Illuminate\Console\Command;

class DataReconcileCommand extends Command
{
    protected $signature = 'laijau:data-reconcile';
    protected $description = 'Verify and reconcile imported business data against source records';

    public function handle(RealDataMigrationService $migrationService): int
    {
        $this->info('================================================================');
        $this->info('  LAIJAU ERP — REAL BUSINESS DATA FINANCIAL & STOCK RECONCILIATION');
        $this->info('================================================================');

        $rec = $migrationService->reconcile();

        $this->newLine();
        $this->info('--- 1. REAL 2026 BUSINESS DATA RECONCILIATION ---');
        $this->table(
            ['Stream', 'Source Files (NPR)', 'Imported 2026 ERP (NPR)', 'Variance (NPR)', 'Audit Status'],
            [
                [
                    'Showroom Gross Sales',
                    number_format($rec['financial_reconciliation']['source_showroom_sales_npr'], 2),
                    number_format($rec['financial_reconciliation']['imported_showroom_sales_npr'], 2),
                    number_format($rec['financial_reconciliation']['sales_variance_npr'], 2),
                    $rec['financial_reconciliation']['sales_reconciled'] ? 'VERIFIED EXACT MATCH' : 'DISCREPANCY',
                ],
                [
                    'January 2026 Purchases',
                    number_format($rec['financial_reconciliation']['source_jan_purchases_npr'], 2),
                    number_format($rec['financial_reconciliation']['imported_jan_purchases_npr'], 2),
                    number_format($rec['financial_reconciliation']['purchases_variance_npr'], 2),
                    $rec['financial_reconciliation']['purchases_reconciled'] ? 'VERIFIED EXACT MATCH' : 'DISCREPANCY',
                ],
            ]
        );

        $this->newLine();
        $this->info('--- 2. MONTH-BY-MONTH SALES RECONCILIATION ---');
        $monthRows = [];
        foreach ($rec['monthly_sales_report'] as $mName => $mData) {
            $monthRows[] = [
                $mName,
                strtoupper($mData['status']),
                number_format($mData['source_total_npr'], 2),
                number_format($mData['erp_total_npr'], 2),
                number_format($mData['variance_npr'], 2),
                $mData['reconciled'] ? 'MATCH' : 'DISCREPANCY',
                $mData['notes'],
            ];
        }
        $this->table(
            ['Month', 'Status', 'Source (NPR)', 'ERP (NPR)', 'Variance', 'Audit', 'Notes'],
            $monthRows
        );

        $this->newLine();
        $this->info('--- 3. DOUBLE-ENTRY ACCOUNTING INTEGRITY ---');
        $this->table(
            ['Total Debits (NPR)', 'Total Credits (NPR)', 'Net Difference (NPR)', 'Status'],
            [
                [
                    number_format($rec['accounting_integrity']['total_debits_npr'], 2),
                    number_format($rec['accounting_integrity']['total_credits_npr'], 2),
                    number_format($rec['accounting_integrity']['difference_npr'], 2),
                    $rec['accounting_integrity']['is_perfectly_balanced'] ? 'PERFECTLY BALANCED (0.00)' : 'IMBALANCE',
                ],
            ]
        );

        $this->newLine();
        $this->info('--- 4. PRODUCTION DATABASE RECORD COUNTS ---');
        $this->table(
            ['Entity', 'Total Records in ERP', 'Context'],
            [
                ['Master Products', $rec['database_counts']['total_products'], 'Footwear & Apparel master catalog'],
                ['Product Variants', $rec['database_counts']['total_variants'], 'Size & Colour SKU variants'],
                ['Online Dispatched Orders', $rec['database_counts']['total_online_orders'], 'Dispatched COD orders via NCM / Pathao'],
                ['System & Customer Users', $rec['database_counts']['total_users'], 'Staff & real verified registered customers'],
            ]
        );

        $this->newLine();
        $this->warn('--- 5. DOCUMENTED BUSINESS EXCEPTIONS ---');
        foreach ($rec['documented_exceptions'] as $key => $desc) {
            $this->line(" * <comment>" . strtoupper(str_replace('_', ' ', $key)) . "</comment>: {$desc}");
        }

        $this->newLine();
        $this->info('Reconciliation report saved to: storage/app/migration/reconciliation_report.json');
        $this->info('Reconciliation completed successfully.');

        return 0;
    }
}
