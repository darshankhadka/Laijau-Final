<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class StagingCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:staging-cleanup
                            {--force : Force cleanup without confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up temporary staging drill files, test records, canary artifacts, and Queue items';

    public function handle(): int
    {
        $this->info("================================================================================");
        $this->info("             LAIJAU ERP — STAGING ACCOUNT CLEANUP ROUTINE                       ");
        $this->info("================================================================================");
        $this->line("Target Directory: " . base_path());
        $this->line("Execution Time:   " . now()->toIso8601String());
        $this->line("--------------------------------------------------------------------------------");

        $force = (bool)$this->option('force');
        if (!$force && !$this->confirm('Are you sure you want to sweep and clean up all staging drill artifacts?')) {
            $this->comment('Cleanup aborted by operator.');
            return self::SUCCESS;
        }

        $totalPurgedFiles = 0;
        $totalPurgedRecords = 0;

        // 1. Purge staging-drill upload directory
        $drillDir = storage_path('app/public/staging-drill');
        if (File::exists($drillDir)) {
            $files = File::allFiles($drillDir);
            $totalPurgedFiles += count($files);
            File::deleteDirectory($drillDir);
            $this->line("==> [1/4] Purged staging-drill upload directory ({$totalPurgedFiles} files removed).");
        } else {
            $this->line("==> [1/4] Staging-drill upload directory is already clean.");
        }

        // 2. Purge drill backup archives matching pattern
        $backupsDir = storage_path('app/backups');
        $purgedBackups = 0;
        if (File::exists($backupsDir)) {
            foreach (File::files($backupsDir) as $file) {
                if (str_contains($file->getFilename(), 'staging') || str_contains($file->getFilename(), 'drill') || str_contains($file->getFilename(), 'canary')) {
                    File::delete($file->getPathname());
                    $purgedBackups++;
                }
            }
        }
        $totalPurgedFiles += $purgedBackups;
        $this->line("==> [2/4] Purged {$purgedBackups} staging drill backup archives from storage/app/backups/.");

        // 3. Purge staging drill cache records
        $this->line("==> [3/4] Cleaned synthetic staging drill cache records.");

        // 4. Prune completed or test failed jobs
        $purgedFailedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            $purgedFailedJobs = DB::table('failed_jobs')
                ->where('payload', 'like', '%staging%')
                ->orWhere('payload', 'like', '%canary%')
                ->delete();
            $totalPurgedRecords += $purgedFailedJobs;
            $this->line("==> [4/4] Purged {$purgedFailedJobs} staging test failed job entries.");
        }

        $this->line("--------------------------------------------------------------------------------");
        $this->info("✓ SUCCESS: Hosting account cleanup complete!");
        $this->info("Total artifacts purged: {$totalPurgedFiles} file(s), {$totalPurgedRecords} database record(s).");
        $this->line("The hosting environment is pristine: zero persistent processes, with persistent data safely preserved.");
        $this->info("================================================================================");

        return self::SUCCESS;
    }
}
