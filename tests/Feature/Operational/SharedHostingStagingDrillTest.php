<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SharedHostingStagingDrillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::forget('system:scheduler:last_heartbeat');
        $this->seed(ModuleSettingsSeeder::class);
    }

    /**
     * Test cPanel rollback script exists, is executable, and passes bash syntax validation.
     */
    public function test_cpanel_rollback_script_syntax_and_integrity(): void
    {
        $rollbackScript = base_path('deploy/cpanel/cpanel-rollback.sh');
        $this->assertFileExists($rollbackScript, 'deploy/cpanel/cpanel-rollback.sh must exist.');
        $this->assertTrue(is_executable($rollbackScript), 'cpanel-rollback.sh must have executable bit set.');

        $output = [];
        $exitCode = 0;
        exec("bash -n " . escapeshellarg($rollbackScript) . " 2>&1", $output, $exitCode);
        $this->assertEquals(0, $exitCode, 'cpanel-rollback.sh must pass bash -n syntax validation.');

        $content = File::get($rollbackScript);
        $this->assertStringContainsString('set -euo pipefail', $content);
        $this->assertStringContainsString('php artisan down', $content);
        $this->assertStringContainsString('php artisan up', $content);
        $this->assertStringContainsString('php artisan erp:restore', $content);
        $this->assertStringContainsString('php artisan optimize:clear', $content);
    }

    /**
     * Test staging drill command executes all 18 probes and outputs the GO LIVE verdict.
     */
    public function test_staging_drill_command_executes_all_18_probes_and_renders_go_live_verdict(): void
    {
        $exitCode = Artisan::call('erp:staging-drill', [
            '--skip-backup' => true,
            '--cleanup' => true,
        ]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode, 'Staging drill command must exit with 0 on clean environment.');

        // Verify all 18 probes appear in output
        $expectedProbes = [
            '1. PHP 8.4 & Extensions',
            '2. Apache + .htaccess',
            '3. MySQL/MariaDB Engine',
            '4. Laravel Migrations',
            '5. Storage & Uploads',
            '6. Checkout & Payments',
            '7. Payment Audit & Webhooks',
            '8. Queue + Cron',
            '9. Scheduled Backups',
            '10. Health Endpoint',
            '11. Encrypted Restore',
            '12. Security Headers',
            '13. File Permissions',
            '14. Zero Persistent Daemons',
            '15. Deployment Automation',
            '16. Rollback Automation',
            '17. Application Smoke Test',
            '18. Resource & Quotas',
        ];

        foreach ($expectedProbes as $probe) {
            $this->assertStringContainsString($probe, $output, "Output must contain probe: {$probe}");
        }

        $this->assertStringContainsString('FINAL VERDICT: [ GO LIVE ]', $output);
        $this->assertStringContainsString('Post-drill teardown complete', $output);
    }

    /**
     * Test staging cleanup command removes all drill artifacts and leaves the account clean.
     */
    public function test_staging_cleanup_command_removes_all_drill_artifacts(): void
    {
        // 1. Seed a canary upload file
        $drillDir = storage_path('app/public/staging-drill');
        if (!File::exists($drillDir)) {
            File::makeDirectory($drillDir, 0775, true);
        }
        $canaryFile = $drillDir . '/canary_test.txt';
        File::put($canaryFile, 'CANARY_TEST');
        $this->assertFileExists($canaryFile);

        // 2. Seed a synthetic staging drill cache record
        $testEventId = 'evt_staging_drill_test_' . uniqid();
        \Illuminate\Support\Facades\Cache::put($testEventId, ['cleanup' => true], 60);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($testEventId));

        // 3. Execute standalone cleanup
        $exitCode = Artisan::call('erp:staging-cleanup', [
            '--force' => true,
        ]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('SUCCESS: Hosting account cleanup complete!', $output);

        // 4. Assert artifacts are purged
        $this->assertFileDoesNotExist($canaryFile);
        $this->assertDirectoryDoesNotExist($drillDir);
    }

    /**
     * Test staging scorecard runbook documentation exists and contains all required sections.
     */
    public function test_staging_scorecard_documentation_and_runbook_integrity(): void
    {
        $scorecardDoc = base_path('deploy/cpanel/REAL_HOST_STAGING_SCORECARD.md');
        $this->assertFileExists($scorecardDoc);

        $content = File::get($scorecardDoc);
        $this->assertStringContainsString('Real Shared-Hosting Staging Scorecard', $content);
        $this->assertStringContainsString('GO LIVE', $content);
        $this->assertStringContainsString('ROLLBACK', $content);
        $this->assertStringContainsString('erp:staging-drill', $content);
        $this->assertStringContainsString('erp:staging-cleanup', $content);
        $this->assertStringContainsString('cpanel-rollback.sh', $content);
    }

    /**
     * Test staging drill detects critical failures, issues ROLLBACK verdict, and returns exit code 1.
     */
    public function test_staging_drill_evaluates_rollback_verdict_on_critical_failure(): void
    {
        $script = base_path('deploy/cpanel/cpanel-rollback.sh');
        $bak = base_path('deploy/cpanel/cpanel-rollback.sh.testbak');
        File::move($script, $bak);

        try {
            $exitCode = Artisan::call('erp:staging-drill', [
                '--skip-backup' => true,
                '--cleanup' => true,
            ]);
            $output = Artisan::output();

            $this->assertEquals(1, $exitCode, 'Drill must return failure code (1) when a critical probe fails.');
            $this->assertStringContainsString('FINAL VERDICT: [ ROLLBACK ]', $output);
            $this->assertStringContainsString('Critical staging failures (1) were detected', $output);
        } finally {
            if (File::exists($bak)) {
                File::move($bak, $script);
            }
        }
    }
}
