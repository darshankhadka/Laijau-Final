<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StagingGoLiveValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
    }

    /**
     * Test explicit PHP 8.4 runtime alignment across composer.json and runtime extensions.
     */
    public function test_php_runtime_alignment_and_version_constraints(): void
    {
        // 1. Host PHP version
        $this->assertGreaterThanOrEqual(80400, PHP_VERSION_ID, 'Runtime must be PHP 8.4+');

        // 2. composer.json platform and require alignment
        $composerJsonPath = base_path('composer.json');
        $this->assertFileExists($composerJsonPath);
        $composer = json_decode(File::get($composerJsonPath), true);

        $this->assertEquals('^8.4', $composer['require']['php']);
        $this->assertArrayHasKey('platform', $composer['config'] ?? []);
        $this->assertEquals('8.4.24', $composer['config']['platform']['php']);

        // 3. Essential extensions
        $requiredExtensions = ['pdo', 'bcmath', 'gd', 'intl', 'zip', 'pcntl'];
        foreach ($requiredExtensions as $ext) {
            $this->assertTrue(extension_loaded($ext), "Core extension [{$ext}] must be loaded.");
        }
    }

    /**
     * Test that erp:staging-verify runs all 10 operational probes and returns exit code 0.
     */
    public function test_staging_verification_command_executes_cleanly(): void
    {
        $exitCode = Artisan::call('erp:staging-verify');
        $this->assertEquals(0, $exitCode, 'Staging verification command must return SUCCESS (0).');

        $output = Artisan::output();
        $this->assertStringContainsString('PRE-FLIGHT GO-LIVE VERIFICATION DRILL', $output);
        $this->assertStringContainsString('PHP 8.4 Runtime & Extensions', $output);
        $this->assertStringContainsString('Secrets & Production Guards', $output);
        $this->assertStringContainsString('Database Connectivity & Schema', $output);
        $this->assertStringContainsString('Cache & Session Store', $output);
        $this->assertStringContainsString('Queue Broker & Dead-Letter Backlog', $output);
        $this->assertStringContainsString('Scheduler Heartbeat & Crond', $output);
        $this->assertStringContainsString('Storage & Media Directory Permissions', $output);
        $this->assertStringContainsString('Disaster Recovery & AES-256 Engine', $output);
        $this->assertStringContainsString('General Ledger Double-Entry Balance', $output);
        $this->assertStringContainsString('SUCCESS: All operational pre-flight probes PASSED', $output);
    }

    /**
     * Test that .env.production.example is sanitized and does not leak secrets into repository.
     */
    public function test_env_production_example_is_clean_and_sanitized(): void
    {
        $examplePath = base_path('.env.production.example');
        $this->assertFileExists($examplePath);

        $content = File::get($examplePath);

        // APP_KEY must be empty
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $content, 'APP_KEY must be empty in example.');

        // Must not contain leaked live/test keys
        $this->assertStringNotContainsString('DlntsU08XKxOzyRulvqkqTLfJrjAEPHre3CEOqfhV+0=', $content);
        $this->assertStringNotContainsString('sk_test_', $content);
        $this->assertStringNotContainsString('sk_live_', $content);

        // Must specify valid production drivers (database/file for shared hosting or redis for VPS)
        $this->assertTrue(
            str_contains($content, 'Queue_CONNECTION=database') || str_contains($content, 'Queue_CONNECTION=redis'),
            'Must specify database or redis Queue connection.'
        );
        $this->assertTrue(
            str_contains($content, 'CACHE_STORE=file') || str_contains($content, 'CACHE_STORE=redis'),
            'Must specify file or redis cache store.'
        );
        $this->assertTrue(
            str_contains($content, 'SESSION_DRIVER=file') || str_contains($content, 'SESSION_DRIVER=redis'),
            'Must specify file or redis session driver.'
        );
        $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY=', $content);
    }

    /**
     * Test docker-compose.staging.yml configuration and port isolation.
     */
    public function test_docker_compose_staging_configuration_integrity(): void
    {
        $composePath = base_path('docker-compose.staging.yml');
        $this->assertFileExists($composePath);

        $content = File::get($composePath);

        // Ports isolated to 8080 and 8443
        $this->assertStringContainsString('"8080:80"', $content);
        $this->assertStringContainsString('"8443:443"', $content);

        // Isolated DB and Redis containers
        $this->assertStringContainsString('staging-db', $content);
        $this->assertStringContainsString('staging-redis', $content);
        $this->assertStringContainsString('staging_db_data:', $content);
        $this->assertStringContainsString('staging_redis_data:', $content);
    }

    /**
     * Test GO_LIVE_RUNBOOK.md document exists and contains all operational cutover phases.
     */
    public function test_go_live_runbook_documentation_integrity(): void
    {
        $runbookPath = base_path('deploy/GO_LIVE_RUNBOOK.md');
        $this->assertFileExists($runbookPath);

        $content = File::get($runbookPath);

        $this->assertStringContainsString('Phase 1: Pre-Flight Certification Gate', $content);
        $this->assertStringContainsString('Phase 2: Production Server Environment & Secrets Setup', $content);
        $this->assertStringContainsString('Phase 3: Pre-Deployment Encrypted Backup Drill', $content);
        $this->assertStringContainsString('Phase 4: Zero-Downtime Deployment Execution', $content);
        $this->assertStringContainsString('Phase 5: Post-Deployment Smoke Verification', $content);
        $this->assertStringContainsString('Phase 6: Emergency Rollback Procedure', $content);
    }
}
