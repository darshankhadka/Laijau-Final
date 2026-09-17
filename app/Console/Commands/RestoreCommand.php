<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:restore 
                            {archive : Path to the backup archive zip file}
                            {--dry-run : Verify manifest and checksum without modifying database}
                            {--connection= : Target database connection name}
                            {--decrypt-key= : Custom decryption key for encrypted archives}
                            {--force : Force restore without interactive confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Restore database and media from an enterprise backup archive';

    public function handle(BackupService $backupService): int
    {
        $archive = (string)$this->argument('archive');
        $dryRun = (bool)$this->option('dry-run');
        $connection = $this->option('connection') ? (string)$this->option('connection') : null;
        $decryptKey = $this->option('decrypt-key') ? (string)$this->option('decrypt-key') : null;
        $force = (bool)$this->option('force');

        // Resolve path if given relative to backups directory
        if (!File::exists($archive)) {
            $candidate = storage_path("app/backups/{$archive}");
            if (File::exists($candidate)) {
                $archive = $candidate;
            } else {
                $this->error("Backup archive not found at: {$archive}");
                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->info("Executing Dry-Run Verification for: {$archive}");
            $result = $backupService->restoreBackup($archive, $connection, dryRun: true, decryptionKey: $decryptKey);
            $this->info("✓ Archive structure and manifest verified!");
            $this->line("SHA256: " . $result['archive_sha256']);
            $this->line("Type: " . $result['manifest']['type']);
            $this->line("Encrypted: " . ($result['encrypted'] ? 'YES' : 'No'));
            $this->line("Tables in backup: " . count($result['manifest']['tables']));
            return self::SUCCESS;
        }

        if (!$force && app()->isProduction()) {
            if (!$this->confirm("WARNING: You are about to overwrite the production database. Are you sure you want to proceed?")) {
                $this->warn("Restore aborted by user.");
                return self::SUCCESS;
            }
        }

        $this->warn("Beginning restoration from: " . basename($archive));

        try {
            $result = $backupService->restoreBackup($archive, $connection, dryRun: false, decryptionKey: $decryptKey);

            $this->info("✓ Restore completed successfully!");
            $this->line("Total rows restored: " . number_format($result['total_rows_restored']));
            $this->line("Total tables restored: " . count($result['tables']));
            $this->line("Encrypted Archive: " . ($result['encrypted'] ? 'YES (Decrypted & Verified)' : 'No'));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Restoration failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
