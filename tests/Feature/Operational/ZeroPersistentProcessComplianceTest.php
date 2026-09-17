<?php

declare(strict_types=1);

namespace Tests\Feature\Operational;

use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Order;
use Database\Seeders\ModuleSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ZeroPersistentProcessComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected array $cleanupArchives = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSettingsSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupArchives as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
        parent::tearDown();
    }

    /**
     * Test Queue worker executes with --stop-when-empty and terminates immediately when no jobs exist.
     */
    public function test_Queue_worker_terminates_immediately_when_empty(): void
    {
        Config::set('queue.default', 'database');

        $start = microtime(true);
        $exitCode = Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--once' => true,
            '--memory' => 1024,
        ]);
        $duration = microtime(true) - $start;

        $this->assertEquals(0, $exitCode, 'Worker must cleanly exit with 0 when empty.');
        $this->assertLessThan(5.0, $duration, 'Worker must not block or hang when Queue is empty.');
    }

    /**
     * Test Queue worker processes pending database job and cleanly terminates without lingering.
     */
    public function test_Queue_worker_processes_job_and_cleanly_exits(): void
    {
        Mail::fake();
        Config::set('queue.default', 'database');

        // Create test order
        $order = Order::create([
            'order_number' => 'ORD-ZERO-DAEMON-01',
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@laijau.com',
            'shipping_address' => 'Baneshwor 10',
            'shipping_country' => 'NP',
            'subtotal' => 500.00,
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'NPR',
            'total_amount' => 500.00,
        ]);

        // Dispatch job onto database Queue
        SendOrderConfirmationEmailJob::dispatch($order->id);

        $this->assertEquals(1, DB::table('jobs')->count(), 'Job must be persisted in database Queue.');

        // Execute worker in self-terminating mode with memory headroom for test runner
        $exitCode = Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 1,
            '--memory' => 1024,
        ]);

        $this->assertEquals(0, $exitCode, 'Worker must exit cleanly with code 0 after processing.');
        $this->assertEquals(0, DB::table('jobs')->count(), 'Job must be removed from Queue table after processing.');
    }

    /**
     * Test backup command executes synchronously within caller process and terminates cleanly.
     */
    public function test_backup_command_executes_synchronously_without_detached_processes(): void
    {
        $backupName = 'test_zero_daemon_backup_' . uniqid() . '.zip';
        $exitCode = Artisan::call('erp:backup', [
            '--type' => 'db',
            '--name' => $backupName,
            '--encrypt' => true,
        ]);

        $this->assertEquals(0, $exitCode, 'Backup must complete synchronously with return code 0.');

        $expectedPath = storage_path("app/backups/{$backupName}.enc");
        $this->assertTrue(File::exists($expectedPath), 'Backup archive must exist upon synchronous completion.');
        $this->cleanupArchives[] = $expectedPath;
    }

    /**
     * Test that erp:staging-verify validates Probe 12 Zero Persistent Daemon Compliance.
     */
    public function test_staging_verify_validates_zero_persistent_process_compliance(): void
    {
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'file');

        $exitCode = Artisan::call('erp:staging-verify');
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('12. Zero Persistent Daemon Compliance', $output);
        $this->assertStringContainsString('PASS', $output);
        $this->assertStringContainsString('self-terminating mode (Zero persistent daemons required)', $output);
    }

    /**
     * Test that cPanel deployment script contains no detached background subshells (&, nohup, disown).
     */
    public function test_cpanel_deploy_script_contains_no_detached_background_operators(): void
    {
        $deployScript = base_path('deploy/cpanel/cpanel-deploy.sh');
        $this->assertFileExists($deployScript);

        $content = File::get($deployScript);

        // Strict shell flags
        $this->assertStringContainsString('set -euo pipefail', $content);

        // Scan for detached operators
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);
            // Skip comments
            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            $this->assertStringNotContainsString('nohup ', $trimmed, "Line " . ($lineNum + 1) . " must not use nohup.");
            $this->assertStringNotContainsString('disown', $trimmed, "Line " . ($lineNum + 1) . " must not use disown.");

            // Ensure no line ends with single & (background process) while ignoring &&
            if (str_ends_with($trimmed, '&') && !str_ends_with($trimmed, '&&')) {
                $this->fail("Line " . ($lineNum + 1) . " ends with background operator '&': {$trimmed}");
            }
        }
    }
}
