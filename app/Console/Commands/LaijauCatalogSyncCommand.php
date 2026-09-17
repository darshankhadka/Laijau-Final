<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\CatalogSyncService;
use Illuminate\Console\Command;

class LaijauCatalogSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laijau:catalog-sync 
                            {--audit : Run audit-only check without applying changes}
                            {--dry-run : Run synchronization in dry-run mode (rolls back transaction)}
                            {--apply : Transactionally apply catalog synchronization}
                            {--export-matrix : Export full product reconciliation matrix to CSV}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Final Laijau ERP product master reconciliation, public catalog synchronization, and data quality pass.';

    /**
     * Execute the console command.
     */
    public function handle(CatalogSyncService $service): int
    {
        $this->info('================================================================');
        $this->info('  LAIJAU ERP — MASTER PRODUCT RECONCILIATION & CATALOG SYNC     ');
        $this->info('================================================================');

        $isAudit = $this->option('audit');
        $isDryRun = $this->option('dry-run');
        $isApply = $this->option('apply');

        if (!$isAudit && !$isDryRun && !$isApply) {
            $this->warn('No execution mode specified. Defaulting to --audit mode.');
            $isAudit = true;
        }

        if ($isAudit) {
            $this->line('Running in AUDIT mode (zero mutations)...');
            $audit = $service->audit();

            $this->newLine();
            $this->table(
                ['Master Reconciliation Dimension', 'Count', 'Status / Verification'],
                [
                    ['Total Real ERP Products', $audit['total_erp_products'], 'Preserved in ERP Master'],
                    ['Total Product Variants', $audit['total_variants'], 'Preserved in ERP Master'],
                    ['Matched to Source (Level 1/2/3)', $audit['matched_count'], 'Verified against Source Sheets'],
                    ['New Source Products (Stock Sheets)', $audit['new_source_count'], 'Imported Stock Batches'],
                    ['Standalone Public Products', $audit['missing_source_count'], 'Retained Showroom Catalog'],
                    ['Staging / Payment Test Items', $audit['staging_count'], 'Flagged for Deactivation'],
                    ['Ready to Publish (Complete + Photo)', $audit['ready_to_publish_count'], 'Eligible for Public Webshop'],
                    ['Missing Photos Only (Complete Data)', $audit['missing_photos_count'], 'Unpublished (Available in POS)'],
                    ['Missing Required Data', $audit['missing_data_count'], 'Staff Data Entry Required'],
                    ['Code Review Required', $audit['code_review_count'], 'Staff SKU Resolution Required'],
                    ['Price Review (Anomalies)', $audit['price_review_count'], 'Excel Source Row Anomalies'],
                    ['Duplicate Candidates', $audit['duplicate_candidates_count'], 'Zero duplicate SKUs'],
                ]
            );

            $this->info('Audit completed. To simulate changes safely, run:');
            $this->line('  php artisan laijau:catalog-sync --dry-run');
            $this->info('To apply changes transactionally, run:');
            $this->line('  php artisan laijau:catalog-sync --apply --export-matrix');
            return Command::SUCCESS;
        }

        $dryRun = $isDryRun || !$isApply;
        $this->line($dryRun ? 'Running in DRY-RUN mode (safe simulation with full rollback)...' : 'APPLYING MASTER RECONCILIATION TRANSACTIONALLY...');

        $result = $service->sync($dryRun, true);
        $m = $result['metrics'];

        $this->newLine();
        $this->info('RECONCILIATION MUTATION METRICS (PHASE 17):');
        $this->table(
            ['Operational Metric', 'Count'],
            [
                ['Products Examined', $m['products_evaluated']],
                ['Products Updated', $m['products_updated']],
                ['Products Unchanged', $m['products_unchanged']],
                ['Products Requiring Review', $m['products_requiring_review']],
                ['Products Marked Unpublished (No Photo / Staging)', $m['products_marked_unpublished']],
                ['Products Activated on Webshop', $m['products_activated']],
                ['Photos Verified on Disk (Filesize > 0)', $m['photos_verified']],
                ['Photos Missing (Filesize == 0 or No File)', $m['photos_missing']],
                ['Stock Records Changed (Showroom Levels)', $m['stock_records_changed']],
                ['Variant Stock Records Changed', $m['variant_stock_records_changed']],
                ['Prices Synchronized from Source', $m['prices_changed']],
                ['Customer Titles Normalized', $m['titles_changed']],
                ['Original Factual Descriptions Generated', $m['descriptions_normalized'] ?? 0],
                ['Categories Reconciled & Attached', $m['categories_assigned'] ?? 0],
                ['Product Types Corrected (Footwear/Apparel)', $m['types_corrected'] ?? 0],
                ['Duplicate Records Reconciled into Master', $m['duplicates_reconciled']],
                ['Historical Sales Re-linked to Master Products', $m['historical_sales_relinked']],
            ]
        );

        $this->newLine();
        $this->info('ARTIFACTS GENERATED:');
        $this->line('  - 27-Column CSV Matrix:    ' . ($result['files_generated']['csv_matrix'] ?? '—'));
        $this->line('  - JSON Report:             ' . ($result['files_generated']['json_report'] ?? '—'));
        $this->line('  - Manual Review CSV:       ' . ($result['files_generated']['manual_review'] ?? '—'));
        $this->line('  - Category Tree Report:    ' . ($result['files_generated']['category_tree'] ?? '—'));
        $this->line('  - Stock Reconcile Report:  ' . ($result['files_generated']['stock_report'] ?? '—'));

        $this->newLine();
        $this->info($dryRun ? 'Dry-run finished successfully. Zero records were mutated in the database.' : 'Master reconciliation successfully committed to database.');

        return Command::SUCCESS;
    }
}
