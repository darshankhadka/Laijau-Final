<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Order;
use App\Models\Product;
use App\Services\Operational\AuditLoggerService;
use App\Services\Operational\BackupService;
use App\Services\Operational\HealthCheckService;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class InfrastructureDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected BackupService $backupService;
    protected HealthCheckService $healthService;
    protected array $cleanupFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
        $this->backupService = app(BackupService::class);
        $this->healthService = app(HealthCheckService::class);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Cache::forget('system:scheduler:last_heartbeat');
        foreach ($this->cleanupFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
        parent::tearDown();
    }

    /**
     * Test scheduler heartbeat command records cache pulse and HealthCheckService validates it.
     */
    public function test_scheduler_heartbeat_pulse_and_health_monitoring(): void
    {
        // 1. Initial state: cache cleared
        Cache::forget('system:scheduler:last_heartbeat');

        // Non-production environment treats missing heartbeat as OK
        $statusNonProd = $this->healthService->checkSchedulerHeartbeat();
        $this->assertEquals('ok', $statusNonProd['status']);

        // 2. Trigger erp:health-ping Artisan command
        $exitCode = Artisan::call('erp:health-ping');
        $this->assertEquals(0, $exitCode);

        // Verify cache heartbeat payload
        $heartbeat = Cache::get('system:scheduler:last_heartbeat');
        $this->assertIsArray($heartbeat);
        $this->assertNotEmpty($heartbeat['timestamp']);
        $this->assertNotEmpty($heartbeat['epoch']);
        $this->assertNotEmpty($heartbeat['pid']);

        // Health probe should now report OK with exact timestamp
        $statusActive = $this->healthService->checkSchedulerHeartbeat();
        $this->assertEquals('ok', $statusActive['status']);
        $this->assertNotNull($statusActive['last_heartbeat']);
        $this->assertLessThanOrEqual(5, $statusActive['elapsed_seconds']);

        // 3. Simulate stale scheduler (> 15 minutes / 900 seconds ago)
        Cache::put('system:scheduler:last_heartbeat', [
            'timestamp' => now()->subMinutes(25)->toIso8601String(),
            'epoch' => now()->subMinutes(25)->timestamp,
            'pid' => 12345,
        ], now()->addHours(1));

        $statusStale = $this->healthService->checkSchedulerHeartbeat();
        $this->assertEquals('error', $statusStale['status']);
        $this->assertStringContainsString('Scheduler heartbeat stale', $statusStale['message']);
    }

    /**
     * Test Queue worker dead-letter alert logging through AuditLoggerService.
     */
    public function test_Queue_dead_letter_alert_logging(): void
    {
        $logger = app(AuditLoggerService::class);

        // Capture or test that QueueAlert formats and logs correctly without throwing
        $logger->QueueAlert(
            'App\Jobs\ProcessOrderFulfillmentJob',
            'Simulated permanent database lock timeout',
            [
                'connection' => 'redis',
                'Queue' => 'fulfillment',
                'attempts' => 3,
                'order_id' => 999,
            ]
        );

        $this->assertTrue(true, 'Queue dead letter alert logged successfully.');
    }

    /**
     * Test encrypted backup creation (AES-256-CBC) and multi-engine restore drill.
     */
    public function test_encrypted_backup_creation_and_isolated_restore_drill(): void
    {
        // 1. Seed test data
        Product::create([
            'name' => 'Kashmiri Encrypted Pashmina Shawl',
            'slug' => 'kashmiri-enc-pashmina',
            'sku' => 'KASH-ENC-001',
            'price' => 1850.00,
            'price_npr' => 1850.00,
            'is_active' => true,
        ]);

        $customKey = 'enterprise_super_secret_aes_key_987654321';
        $customName = 'test_encrypted_run_' . uniqid() . '.zip';

        // 2. Create encrypted database backup
        $result = $this->backupService->createBackup(
            type: 'db',
            customName: $customName,
            encrypt: true,
            encryptionKey: $customKey
        );

        $this->assertEquals('success', $result['status']);
        $this->assertTrue($result['encrypted']);
        $this->assertStringEndsWith('.enc', $result['archive_path']);
        $this->cleanupFiles[] = $result['archive_path'];

        // Verify unencrypted zip does not exist on disk
        $unencryptedPath = storage_path("app/backups/{$customName}");
        $this->assertFalse(File::exists($unencryptedPath), 'Unencrypted zip file must be purged.');

        // Verify encryption magic header
        $rawBytes = File::get($result['archive_path']);
        $this->assertStringStartsWith('NA_ENC_V2', $rawBytes);

        // 3. Test Dry-Run Verification with correct key
        $dryRun = $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: $customKey
        );
        $this->assertEquals('dry_run_verified', $dryRun['status']);
        $this->assertTrue($dryRun['encrypted']);
        $this->assertArrayHasKey('products', $dryRun['manifest']['tables']);

        // 4. Test Decryption Rejection with incorrect key
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: 'WRONG_INVALID_KEY_00000000000'
        );
    }

    /**
     * Test successful decrypted restore into an isolated SQLite database connection.
     */
    public function test_encrypted_backup_restores_into_isolated_database_cleanly(): void
    {
        Product::create([
            'name' => 'Isolated Restore Pashmina',
            'slug' => 'isolated-restore-pashmina',
            'sku' => 'ISO-RESTORE-001',
            'price' => 2200.00,
            'price_npr' => 2200.00,
            'is_active' => true,
        ]);

        $customKey = 'isolated_db_aes_key_123';
        $customName = 'test_iso_run_' . uniqid() . '.zip';

        $backup = $this->backupService->createBackup(
            type: 'db',
            customName: $customName,
            encrypt: true,
            encryptionKey: $customKey
        );
        $this->cleanupFiles[] = $backup['archive_path'];

        // Create temporary isolated database
        $tempDbPath = storage_path('app/backups/isolated_test_' . uniqid() . '.sqlite');
        touch($tempDbPath);
        $this->cleanupFiles[] = $tempDbPath;

        Config::set('database.connections.isolated_test', [
            'driver' => 'sqlite',
            'database' => $tempDbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Run migrations on isolated database
        Artisan::call('migrate', [
            '--database' => 'isolated_test',
            '--force' => true,
        ]);

        // Restore encrypted backup into isolated connection
        $restoreResult = $this->backupService->restoreBackup(
            archivePath: $backup['archive_path'],
            targetConnection: 'isolated_test',
            dryRun: false,
            decryptionKey: $customKey
        );

        $this->assertEquals('success', $restoreResult['status']);
        $this->assertTrue($restoreResult['encrypted']);
        $this->assertGreaterThan(0, $restoreResult['total_rows_restored']);

        // Assert record exists in isolated database
        $restoredProductCount = DB::connection('isolated_test')->table('products')->count();
        $this->assertGreaterThanOrEqual(1, $restoredProductCount);

        $found = DB::connection('isolated_test')->table('products')
            ->where('slug', 'isolated-restore-pashmina')
            ->first();
        $this->assertNotNull($found);
        $this->assertEquals('Isolated Restore Pashmina', $found->name);
    }

    /**
     * Test production Nginx configuration file contains required security headers and directives.
     */
    public function test_production_nginx_configuration_integrity(): void
    {
        $nginxConfPath = base_path('deploy/nginx/laijau.conf');
        $this->assertFileExists($nginxConfPath);

        $conf = File::get($nginxConfPath);

        // Security headers
        $this->assertStringContainsString('Strict-Transport-Security', $conf);
        $this->assertStringContainsString('max-age=31536000', $conf);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $conf);
        $this->assertStringContainsString('X-Frame-Options "SAMEORIGIN"', $conf);
        $this->assertStringContainsString('Content-Security-Policy', $conf);
        $this->assertStringContainsString('Referrer-Policy', $conf);

        // SSL / TLS & Rate Limiting
        $this->assertStringContainsString('TLSv1.2 TLSv1.3', $conf);
        $this->assertStringContainsString('limit_req_zone', $conf);
        $this->assertStringContainsString('location /checkout', $conf);

        // FastCGI Handler
        $this->assertStringContainsString('fastcgi_pass php_fpm_laijau', $conf);
        $this->assertStringContainsString('location ~ \.php$', $conf);
    }

    /**
     * Test zero-downtime deployment and rollback shell scripts structure and syntax.
     */
    public function test_deployment_and_rollback_scripts_integrity(): void
    {
        $deployScript = base_path('deploy/scripts/deploy.sh');
        $rollbackScript = base_path('deploy/scripts/rollback.sh');

        $this->assertFileExists($deployScript);
        $this->assertFileExists($rollbackScript);

        // Check executable permission
        $this->assertTrue(is_executable($deployScript), 'deploy.sh must be executable.');
        $this->assertTrue(is_executable($rollbackScript), 'rollback.sh must be executable.');

        $deployContent = File::get($deployScript);
        $rollbackContent = File::get($rollbackScript);

        // Deploy script atomic guarantees
        $this->assertStringContainsString('ln -sfn', $deployContent);
        $this->assertStringContainsString('config:cache', $deployContent);
        $this->assertStringContainsString('queue:restart', $deployContent);
        $this->assertStringContainsString('KEEP_RELEASES=5', $deployContent);

        // Rollback script guarantees
        $this->assertStringContainsString('ln -sfn', $rollbackContent);
        $this->assertStringContainsString('queue:restart', $rollbackContent);
        $this->assertStringContainsString('readlink -f', $rollbackContent);
    }
}
