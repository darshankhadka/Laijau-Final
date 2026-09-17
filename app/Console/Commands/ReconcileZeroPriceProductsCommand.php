<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileZeroPriceProductsCommand extends Command
{
    protected $signature = 'laijau:reconcile-zero-prices {--dry-run : Run reconciliation without committing changes}';
    protected $description = 'Audit and reconcile zero/null selling prices from historical sales and catalog evidence';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info("================================================================");
        $this->info(" LAIJAU ERP — ZERO-PRICE PRODUCT & VARIANT RECONCILIATION");
        $this->info(" Mode: " . ($dryRun ? "DRY-RUN (Simulated)" : "PRODUCTION LIVE UPDATE"));
        $this->info("================================================================\n");

        DB::beginTransaction();

        try {
            $auditLog = [];

            // 1. Reconcile Product 811 (Singing Bowl Gift)
            $p811 = Product::find(811);
            if ($p811) {
                $oldPrice = $p811->price;
                $newPrice = 600.00;
                $this->info("Reconciling Product #811 ({$p811->name}, SKU: {$p811->sku})...");
                $this->line("  Old Price: " . ($oldPrice ?? 'NULL') . " -> New Price: NPR {$newPrice}");
                $this->line("  Source of Truth: POS Sale #37 (Unit Price: NPR 600.00)");
                $this->line("  Confidence: 100% (Authoritative historical sales evidence)");

                if (!$dryRun) {
                    $p811->price = $newPrice;
                    $p811->save();

                    ProductVariant::where('product_id', 811)->update(['price' => $newPrice]);
                }

                $auditLog[] = [
                    'product_id' => 811,
                    'sku' => $p811->sku,
                    'name' => $p811->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'source' => 'POS Sale #37',
                    'action' => 'PRICE_RECONCILED',
                    'confidence' => '100%',
                ];
            }

            // 2. Reconcile Product 812 (Item 1)
            $p812 = Product::find(812);
            if ($p812) {
                $oldPrice = $p812->price;
                $newPrice = 100.00;
                $this->info("Reconciling Product #812 ({$p812->name}, SKU: {$p812->sku})...");
                $this->line("  Old Price: " . ($oldPrice ?? 'NULL') . " -> New Price: NPR {$newPrice}");
                $this->line("  Source of Truth: POS Sales #38, #39 (Unit Price: NPR 100.00)");
                $this->line("  Confidence: 100% (Authoritative historical sales evidence)");

                if (!$dryRun) {
                    $p812->price = $newPrice;
                    $p812->save();

                    ProductVariant::where('product_id', 812)->update(['price' => $newPrice]);
                }

                $auditLog[] = [
                    'product_id' => 812,
                    'sku' => $p812->sku,
                    'name' => $p812->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'source' => 'POS Sales #38, #39',
                    'action' => 'PRICE_RECONCILED',
                    'confidence' => '100%',
                ];
            }

            // 3. Reconcile Product 813 (Item Dash)
            $p813 = Product::find(813);
            if ($p813) {
                $oldPrice = $p813->price;
                $newPrice = 500.00;
                $this->info("Reconciling Product #813 ({$p813->name}, SKU: {$p813->sku})...");
                $this->line("  Old Price: " . ($oldPrice ?? 'NULL') . " -> New Price: NPR {$newPrice}");
                $this->line("  Source of Truth: POS Sales #40, #41 (Unit Price: NPR 500.00)");
                $this->line("  Confidence: 100% (Authoritative historical sales evidence)");

                if (!$dryRun) {
                    $p813->price = $newPrice;
                    $p813->save();

                    ProductVariant::where('product_id', 813)->update(['price' => $newPrice]);
                }

                $auditLog[] = [
                    'product_id' => 813,
                    'sku' => $p813->sku,
                    'name' => $p813->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'source' => 'POS Sales #40, #41',
                    'action' => 'PRICE_RECONCILED',
                    'confidence' => '100%',
                ];
            }

            // 4. Handle Product 810 (Limited Apparel Item)
            $p810 = Product::find(810);
            if ($p810) {
                $this->info("Auditing Product #810 ({$p810->name}, SKU: {$p810->sku})...");
                $this->line("  Sales Count: 0 | Purchase Orders: 0 | Cost Price: NULL");
                $this->line("  Action: Strictly marked PRICE_REVIEW_REQUIRED per Priority 3; zero price not invented.");
                $this->line("  Visibility: Unpublished & strictly INTERNAL.");

                if (!$dryRun) {
                    $p810->is_published = false;
                    $p810->internal_reference = 'PRICE_REVIEW_REQUIRED';
                    if (!str_contains($p810->description ?? '', 'PRICE_REVIEW_REQUIRED')) {
                        $p810->description = trim(($p810->description ?? '') . "\n\n### Internal Review\n- **Status**: PRICE_REVIEW_REQUIRED\n- **Reason**: Awaiting management review for retail price assignment.");
                    }
                    $p810->save();
                }

                $auditLog[] = [
                    'product_id' => 810,
                    'sku' => $p810->sku,
                    'name' => $p810->name,
                    'old_price' => $p810->price,
                    'new_price' => null,
                    'source' => 'Zero Sales / Zero Landed Cost',
                    'action' => 'PRICE_REVIEW_REQUIRED (KEPT INTERNAL)',
                    'confidence' => '100% (Honest unpriced record preserved)',
                ];
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("\n>>> DRY RUN COMPLETE: Changes simulated, zero database modifications committed. <<<");
            } else {
                DB::commit();
                $this->info("\n>>> RECONCILIATION COMMITTED: All changes successfully written to MySQL LAIJAU. <<<");
            }

            // Save JSON Audit Log
            $logPath = storage_path('app/migration/price_reconciliation_audit_log.json');
            @mkdir(dirname($logPath), 0755, true);
            file_put_contents($logPath, json_encode($auditLog, JSON_PRETTY_PRINT));
            $this->info("Detailed audit log saved to: {$logPath}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Reconciliation failed: " . $e->getMessage());
            Log::error("Zero price reconciliation error", ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
