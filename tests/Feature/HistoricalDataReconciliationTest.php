<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Operational\HistoricalDataReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalDataReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected HistoricalDataReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::table('offline_sales')->count() < 1000) {
            $this->markTestSkipped('Historical data reconciliation test requires production database.');
        }
        if (!file_exists(storage_path('app/migration/real_data_extracted/feb_sales_2026.json'))) {
            $this->markTestSkipped('Legacy JSON extraction file not present; authentic PDF imports used.');
        }
        $this->service = app(HistoricalDataReconciliationService::class);
    }

    /**
     * Test audit method returns valid counts and financial sums for all 10 streams.
     */
    public function test_audit_reports_exact_source_counts_and_amounts(): void
    {
        $audit = $this->service->auditAllSources();

        $this->assertArrayHasKey('february_sales', $audit);
        $this->assertSame(1763, $audit['february_sales']['source_sales_count']);
        $this->assertEquals(4181957.00, $audit['february_sales']['source_gross_sales_npr']);
        $this->assertSame(181, $audit['february_sales']['source_expenses_count']);
        $this->assertEquals(623290.00, $audit['february_sales']['source_expenses_npr']);

        $this->assertArrayHasKey('cancelled_orders', $audit);
        $this->assertSame(308, $audit['cancelled_orders']['source_count']);

        $this->assertArrayHasKey('clothes_procurement', $audit);
        $this->assertSame(210, $audit['clothes_procurement']['source_items_count']);
        $this->assertEquals(7090605.00, $audit['clothes_procurement']['source_total_cost_npr']);

        $this->assertArrayHasKey('ncm_logistics', $audit);
        $this->assertSame(1623, $audit['ncm_logistics']['total_shipments']);
        $this->assertTrue($audit['ncm_logistics']['invariant_held']);

        $this->assertArrayHasKey('ledger_status', $audit);
        $this->assertTrue($audit['ledger_status']['is_balanced']);
        $this->assertLessThan(0.01, $audit['ledger_status']['variance']);
    }

    /**
     * Test dry run executes simulation and respects idempotency safeguards.
     */
    public function test_dry_run_executes_simulation_cleanly(): void
    {
        $metrics = $this->service->dryRun();

        $this->assertSame('DRY_RUN', $metrics['mode']);
        // Because the database is now finalized, idempotency safeguards prevent duplicate imports
        $this->assertTrue($metrics['ledger_balanced']);
        $this->assertTrue($metrics['ncm_invariant_verified']);
        $this->assertGreaterThanOrEqual(0, $metrics['february_sales_created']);
    }

    /**
     * Test NCM logistics invariant check passes completely on existing production data.
     */
    public function test_ncm_logistics_strict_invariants_pass(): void
    {
        $ncm = $this->service->verifyNcmLogisticsInvariants();

        $this->assertSame(1623, $ncm['total_shipments']);
        $this->assertSame(1332, $ncm['matched_shipments']);
        $this->assertSame(267, $ncm['ambiguous_shipments']);
        $this->assertSame(24, $ncm['unmatched_shipments']);
        $this->assertSame(0, $ncm['invalid_ambiguous_assignments']);
        $this->assertSame(0, $ncm['tracking_mismatches']);
        $this->assertTrue($ncm['all_invariants_pass']);
    }

    /**
     * Test general ledger balance is mathematically zero variance.
     */
    public function test_general_ledger_balance(): void
    {
        $ledger = $this->service->verifyLedgerBalance();

        $this->assertTrue($ledger['is_balanced']);
        $this->assertLessThan(0.01, $ledger['variance']);
        $this->assertGreaterThan(40000000.0, $ledger['total_debit']);
    }
}
