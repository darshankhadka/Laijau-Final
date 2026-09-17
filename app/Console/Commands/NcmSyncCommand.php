<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Logistics\NcmReconciliationService;
use Illuminate\Console\Command;

class NcmSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ncm:sync
                            {file? : Path to NCM real data CSV file}
                            {--dry-run : Run parsing and matching audit without writing mutations}
                            {--apply : Transactionally apply shipments and order synchronizations}
                            {--batch-id= : Custom operational batch identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit, match, and synchronize NCM logistics shipments into Laijau ERP with idempotency';

    /**
     * Execute the console command.
     */
    public function handle(NcmReconciliationService $service): int
    {
        $file = $this->argument('file');
        if (!$file) {
            $defaultFile = base_path('Real Laijau Data/ncm real data.csv');
            if (file_exists($defaultFile)) {
                $file = $defaultFile;
            } else {
                $this->error("Please specify an NCM CSV file path. Default 'Real Laijau Data/ncm real data.csv' not found.");
                return 1;
            }
        }

        if (!file_exists($file)) {
            $this->error("File not found at: {$file}");
            return 1;
        }

        $apply = (bool)$this->option('apply');
        $dryRun = (bool)$this->option('dry-run');

        // Safety default: if not explicitly --apply, run in dry-run mode
        if (!$apply) {
            $dryRun = true;
        }

        $batchId = $this->option('batch-id') ?: 'ncm_sync_' . date('Ymd_His');

        $this->newLine();
        $this->info("================================================================");
        $this->info("      LAIJAU ERP — NCM LOGISTICS RECONCILIATION & SYNC          ");
        $this->info("================================================================");
        $this->line("Target File: " . realpath($file));
        $this->line("Batch ID:    {$batchId}");
        $this->line("Mode:        " . ($apply ? "<fg=red;options=bold>TRANSACTIONAL APPLY</>" : "<fg=yellow;options=bold>DRY-RUN AUDIT (0 mutations)</>"));
        $this->newLine();

        $this->output->write("Processing NCM dataset and matching with Laijau orders... ");

        try {
            $metrics = $service->sync($file, $apply, $batchId);
            $this->info("DONE.");
            $this->newLine();
        } catch (\Throwable $e) {
            $this->error("FAILED!");
            $this->error($e->getMessage());
            return 1;
        }

        // 1. Operational Matching Table
        $this->info("NCM LOGISTICS SYNCHRONIZATION METRICS:");
        $this->table(
            ['Operational Metric', 'Count / Value'],
            [
                ['Source Rows Processed', number_format($metrics['source_rows'])],
                ['Unique NCM Shipments', number_format($metrics['unique_ncm_shipments'])],
                ['Shipments Created in DB', number_format($metrics['created'])],
                ['Shipments Updated in DB', number_format($metrics['updated'])],
                ['Shipments Unchanged in DB', number_format($metrics['unchanged'])],
                ['Orders Synchronized (Carrier/Tracking)', number_format($metrics['orders_synchronized'])],
            ]
        );

        $this->newLine();

        // 2. Matching Hierarchy Breakdown Table
        $this->info("EVIDENCE-BASED ORDER MATCHING BREAKDOWN:");
        $this->table(
            ['Matching Classification', 'Count', 'Status / Action Required'],
            [
                ['Level 1: Exact Tracking / Courier ID', number_format($metrics['matched_exact']), 'Exact High-Confidence Match'],
                ['Level 3a: Unique Customer Phone', number_format($metrics['matched_high']), 'High-Confidence Single Order Match'],
                ['Level 3b: Multi-Phone Disambiguated', number_format($metrics['matched_medium']), 'Disambiguated by COD / Date Proximity'],
                ['Total Confirmed Order Matches', number_format($metrics['matched_total']), 'Synchronized to Laijau Orders'],
                ['Ambiguous Matches (Multiple Candidates)', number_format($metrics['ambiguous']), 'Routed to Manual Review Required CSV'],
                ['Unmatched NCM Shipments (No Order Match)', number_format($metrics['unmatched']), 'Retained as Independent NCM Shipments'],
            ]
        );

        $this->newLine();

        // 3. Delivery & Status Breakdown Table
        $sb = $metrics['status_breakdown'];
        $this->info("DELIVERY STATUS & LOGISTICS BREAKDOWN:");
        $this->table(
            ['Status / Condition', 'Count'],
            [
                ['Delivered (with verified delivery date)', number_format($sb['delivered'])],
                ['Out for Delivery (Sent for Delivery)', number_format($sb['out_for_delivery'])],
                ['Dispatched (In Transit)', number_format($sb['dispatched'])],
                ['Arrived at Hub / Branch', number_format($sb['arrived'])],
                ['Pickup Pending (Sent for Pickup)', number_format($sb['pickup_pending'])],
                ['Vendor Returns (Flagged as Returned)', number_format($metrics['vendor_returns_count'])],
                ['Other / Unknown Statuses', number_format($sb['other'])],
            ]
        );

        $this->newLine();

        // 4. Financial & Physical Totals Table
        $this->info("FINANCIAL & PHYSICAL TOTALS:");
        $this->table(
            ['Dimension', 'Authoritative Value'],
            [
                ['Total NCM COD Amount', 'NPR ' . number_format($metrics['cod_total'], 2)],
                ['Total NCM Delivery Charges', 'NPR ' . number_format($metrics['delivery_charge_total'], 2)],
                ['Total Package Weight', number_format($metrics['weight_total'], 2) . ' kg'],
            ]
        );

        $this->newLine();
        $this->info("GENERATED AUDIT ARTIFACTS:");
        $this->line(" - 28-Column Matrix: storage/app/migration/ncm_reconciliation_matrix.csv");
        $this->line(" - Manual Review CSV: storage/app/migration/ncm_manual_review_required.csv");
        $this->line(" - JSON Summary:      storage/app/migration/ncm_reconciliation_report.json");
        $this->newLine();

        if ($dryRun && !$apply) {
            $this->comment("Dry-run complete. To apply these records transactionally to the database, run:");
            $this->line("  <info>php artisan ncm:sync \"" . $file . "\" --apply</info>");
        } else {
            $this->info("NCM logistics records successfully synchronized and committed.");
        }

        return 0;
    }
}
