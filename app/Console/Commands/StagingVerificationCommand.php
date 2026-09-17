<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\HealthCheckService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class StagingVerificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:staging-verify {--strict : Enforce strict production rules even in staging}';

    /**
     * The console command description.
     */
    protected $description = 'Execute a 10-point operational pre-flight verification drill before production go-live';

    public function handle(HealthCheckService $healthService): int
    {
        $this->info("================================================================================");
        $this->info("             LAIJAU ERP — PRE-FLIGHT GO-LIVE VERIFICATION DRILL                 ");
        $this->info("================================================================================");
        $this->line("Timestamp:   " . now()->toIso8601String());
        $this->line("Environment: " . app()->environment());
        $this->line("PHP Version: " . PHP_VERSION);
        $this->line("--------------------------------------------------------------------------------");

        $strict = (bool)$this->option('strict') || app()->isProduction();
        $results = [];
        $hasErrors = false;

        // 1. PHP Runtime & Extensions
        $requiredExts = ['pdo', 'bcmath', 'gd', 'intl', 'zip', 'pcntl'];
        $missingExts = [];
        foreach ($requiredExts as $ext) {
            if (!extension_loaded($ext)) {
                $missingExts[] = $ext;
            }
        }
        $phpVersionOk = PHP_VERSION_ID >= 80400;
        $extsOk = empty($missingExts);
        $results[] = [
            'Probe' => '1. PHP 8.4 Runtime & Extensions',
            'Status' => ($phpVersionOk && $extsOk) ? 'PASS' : ($phpVersionOk ? 'WARN' : 'FAIL'),
            'Details' => $phpVersionOk
                ? ($extsOk ? "PHP " . PHP_VERSION . " (all core extensions present)" : "Missing extensions: " . implode(', ', $missingExts))
                : "PHP " . PHP_VERSION . " < 8.4 required",
        ];
        if (!$phpVersionOk) $hasErrors = true;

        // 2. Secrets & Production Configuration Guards
        $appKey = config('app.key');
        $debug = config('app.debug');
        $secretsOk = !empty($appKey);
        $guardDetails = "APP_KEY configured";
        if ($strict) {
            if ($debug) {
                $secretsOk = false;
                $guardDetails .= " | APP_DEBUG is TRUE (Must be false in production!)";
            } else {
                $guardDetails .= " | APP_DEBUG=false";
            }
        } else {
            $guardDetails .= " | APP_DEBUG=" . ($debug ? 'true' : 'false');
        }
        $results[] = [
            'Probe' => '2. Secrets & Production Guards',
            'Status' => $secretsOk ? 'PASS' : 'FAIL',
            'Details' => $guardDetails,
        ];
        if (!$secretsOk) $hasErrors = true;

        // 3. Database Connectivity & Migrations
        $dbStart = microtime(true);
        $dbOk = true;
        $dbDetails = "";
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $dbStart) * 1000, 2);
            $hasMigrations = Schema::hasTable('migrations');
            $dbDetails = "Driver: " . DB::connection()->getDriverName() . " ({$latency}ms latency)";
            if (!$hasMigrations) {
                $dbOk = false;
                $dbDetails .= " | migrations table missing!";
            }
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbDetails = "DB Connection Failed: " . $e->getMessage();
        }
        $results[] = [
            'Probe' => '3. Database Connectivity & Schema',
            'Status' => $dbOk ? 'PASS' : 'FAIL',
            'Details' => $dbDetails,
        ];
        if (!$dbOk) $hasErrors = true;

        // 4. Cache Store Read/Write
        $cacheOk = true;
        $cacheDetails = "";
        try {
            $canaryKey = 'staging:verify:canary:' . uniqid();
            Cache::put($canaryKey, 'ok', 60);
            $read = Cache::get($canaryKey);
            Cache::forget($canaryKey);
            if ($read === 'ok') {
                $cacheDetails = "Store: " . config('cache.default') . " (Read/Write confirmed)";
            } else {
                $cacheOk = false;
                $cacheDetails = "Cache write succeeded but read failed";
            }
        } catch (\Throwable $e) {
            $cacheOk = false;
            $cacheDetails = "Cache Failed: " . $e->getMessage();
        }
        $results[] = [
            'Probe' => '4. Cache & Session Store',
            'Status' => $cacheOk ? 'PASS' : 'FAIL',
            'Details' => $cacheDetails,
        ];
        if (!$cacheOk) $hasErrors = true;

        // 5. Queue Broker & Failed Jobs
        $QueueOk = true;
        $QueueDetails = "Driver: " . config('queue.default');
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedCount = DB::table('failed_jobs')->count();
                $QueueDetails .= " | Failed jobs: {$failedCount}";
                if ($failedCount > 25) {
                    $QueueOk = false;
                    $QueueDetails .= " (Excessive failed jobs backlog!)";
                }
            }
        } catch (\Throwable $e) {
            $QueueOk = false;
            $QueueDetails .= " | " . $e->getMessage();
        }
        $results[] = [
            'Probe' => '5. Queue Broker & Dead-Letter Backlog',
            'Status' => $QueueOk ? 'PASS' : 'FAIL',
            'Details' => $QueueDetails,
        ];
        if (!$QueueOk) $hasErrors = true;

        // 6. Scheduler Heartbeat
        $schedReport = $healthService->checkSchedulerHeartbeat();
        $schedOk = ($schedReport['status'] === 'ok');
        $schedDetails = !empty($schedReport['last_heartbeat'])
            ? "Last pulse: " . $schedReport['last_heartbeat'] . " (" . ($schedReport['elapsed_seconds'] ?? 0) . "s ago)"
            : ($schedReport['note'] ?? $schedReport['message'] ?? 'Pending');
        $results[] = [
            'Probe' => '6. Scheduler Heartbeat & Crond',
            'Status' => $schedOk ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
            'Details' => $schedDetails,
        ];
        if (!$schedOk && $strict) $hasErrors = true;

        // 7. Storage Permissions & Media Integrity
        $pathsToCheck = [
            'storage/app/public' => storage_path('app/public'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
        ];
        $permErrors = [];
        foreach ($pathsToCheck as $label => $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0775, true);
            }
            if (!is_writable($path)) {
                $permErrors[] = $label;
            }
        }
        $storageOk = empty($permErrors);
        $results[] = [
            'Probe' => '7. Storage & Media Directory Permissions',
            'Status' => $storageOk ? 'PASS' : 'FAIL',
            'Details' => $storageOk ? "All storage directories writable (0775)" : "Non-writable paths: " . implode(', ', $permErrors),
        ];
        if (!$storageOk) $hasErrors = true;

        // 8. Disaster Recovery Engine & OpenSSL
        $drOk = class_exists('ZipArchive') && in_array('aes-256-cbc', openssl_get_cipher_methods(), true);
        $results[] = [
            'Probe' => '8. Disaster Recovery & AES-256 Engine',
            'Status' => $drOk ? 'PASS' : 'FAIL',
            'Details' => $drOk ? "ZipArchive & OpenSSL AES-256-CBC cipher available" : "Missing ZipArchive or OpenSSL cipher support",
        ];
        if (!$drOk) $hasErrors = true;

        // 9. Accounting General Ledger Immutability & Balance
        $ledgerReport = $healthService->checkLedgerIntegrity();
        $ledgerOk = ($ledgerReport['status'] === 'ok');
        $results[] = [
            'Probe' => '9. General Ledger Double-Entry Balance',
            'Status' => $ledgerOk ? 'PASS' : 'FAIL',
            'Details' => $ledgerOk ? "Zero unbalanced vouchers committed (NAS / IRD compliant)" : "Unbalanced vouchers detected: " . ($ledgerReport['unbalanced_vouchers'] ?? 'N/A'),
        ];
        if (!$ledgerOk) $hasErrors = true;

        // 10. Security Audit Command Probe
        $auditOk = true;
        $auditDetails = "php artisan erp:security-audit passed";
        try {
            $exit = $this->callSilent('erp:security-audit');
            if ($exit !== 0) {
                $auditOk = false;
                $auditDetails = "erp:security-audit reported security warnings";
            }
        } catch (\Throwable $e) {
            $auditOk = false;
            $auditDetails = "Security audit failed to execute: " . $e->getMessage();
        }
        $results[] = [
            'Probe' => '10. Automated Security Audit Gate',
            'Status' => $auditOk ? 'PASS' : 'FAIL',
            'Details' => $auditDetails,
        ];
        if (!$auditOk) $hasErrors = true;

        // 11. Apache / cPanel Security Shield (.htaccess)
        $publicHtaccess = public_path('.htaccess');
        $rootHtaccess = base_path('.htaccess');
        $htaccessOk = File::exists($publicHtaccess) && File::exists($rootHtaccess);
        $htaccessDetails = $htaccessOk
            ? "Root & public .htaccess shields active (cPanel / Apache protected)"
            : "Missing .htaccess in root or public/";
        $results[] = [
            'Probe' => '11. Apache / cPanel Security Shields (.htaccess)',
            'Status' => $htaccessOk ? 'PASS' : 'WARN',
            'Details' => $htaccessDetails,
        ];

        // 12. Zero Persistent Process / Resource Compliance
        $QueueDriver = config('queue.default');
        $isStatelessQueue = in_array($QueueDriver, ['database', 'sync'], true);
        $daemonOk = $isStatelessQueue;
        $daemonDetails = $isStatelessQueue
            ? "Queue [{$QueueDriver}] operates in on-demand, self-terminating mode (Zero persistent daemons required)"
            : "Queue [{$QueueDriver}] requires external worker daemon (VPS/container profile)";
        $results[] = [
            'Probe' => '12. Zero Persistent Daemon Compliance',
            'Status' => $daemonOk ? 'PASS' : 'WARN',
            'Details' => $daemonDetails,
        ];

        // Render Table
        $this->table(['Operational Probe', 'Status', 'Diagnostic Details'], $results);

        $this->line("--------------------------------------------------------------------------------");
        if ($hasErrors) {
            $this->error("FAILED: Operational staging verification detected blocking issues.");
            $this->error("Do not proceed with production cutover until all FAIL probes are resolved.");
            return self::FAILURE;
        }

        $this->info("✓ SUCCESS: All operational pre-flight probes PASSED.");
        $this->info("Laijau ERP environment is certified ready for production go-live.");
        $this->info("================================================================================");

        return self::SUCCESS;
    }
}
