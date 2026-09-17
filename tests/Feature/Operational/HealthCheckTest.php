<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Models\Accounting\JournalEntry;
use App\Services\Operational\HealthCheckService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected HealthCheckService $healthService;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::forget('system:scheduler:last_heartbeat');
        $this->seed(ModuleSettingsSeeder::class);
        $this->healthService = app(HealthCheckService::class);
    }

    /**
     * Test /api/health endpoint returns 200 and healthy JSON report under standard operations.
     */
    public function test_health_endpoint_returns_ok_when_system_healthy(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'version',
            'subsystems' => [
                'database',
                'cache',
                'storage',
                'migrations',
                'Queue',
                'accounting_ledger',
            ],
        ]);

        $this->assertEquals('healthy', $response->json('status'));
        $this->assertEquals('ok', $response->json('subsystems.database.status'));
        $this->assertEquals('ok', $response->json('subsystems.cache.status'));
        $this->assertEquals('ok', $response->json('subsystems.storage.status'));
    }

    /**
     * Test health check service flags unbalanced vouchers if ledger integrity is compromised.
     */
    public function test_health_service_detects_unbalanced_ledger_vouchers(): void
    {
        $initial = $this->healthService->checkLedgerIntegrity();
        $this->assertEquals('ok', $initial['status']);

        // Directly insert an unbalanced posted entry simulating historical corruption
        $period = app(\App\Services\Accounting\AccountingService::class)->resolvePeriodForDate(date('Y-m-d'));

        DB::table('accounting_journal_entries')->insert([
            'entry_number' => 'BIL-CORRUPT-001',
            'voucher_date' => date('Y-m-d'),
            'accounting_period_id' => $period->id,
            'entry_type' => 'manual',
            'description' => 'Simulated corrupt entry',
            'currency' => 'npr',
            'total_debit' => 1000.00,
            'total_credit' => 500.00,
            'is_balanced' => false,
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $degraded = $this->healthService->checkLedgerIntegrity();
        $this->assertEquals('error', $degraded['status']);
        $this->assertGreaterThan(0, $degraded['unbalanced_vouchers']);

        $overall = $this->healthService->check();
        $this->assertEquals('degraded', $overall['status']);
    }
}
