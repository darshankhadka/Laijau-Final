<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operational\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:backup 
                            {--type=all : Backup type: all, db, or media}
                            {--name= : Optional custom archive filename}
                            {--encrypt : Encrypt the backup archive using AES-256-CBC}
                            {--key= : Custom encryption key (defaults to APP_KEY)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate an enterprise database and media backup archive';

    public function handle(BackupService $backupService): int
    {
        $type = (string)$this->option('type');
        $name = $this->option('name') ? (string)$this->option('name') : null;
        $encrypt = (bool)$this->option('encrypt');
        $key = $this->option('key') ? (string)$this->option('key') : null;

        $this->info("Initiating Laijau ERP Backup [Type: {$type}]" . ($encrypt ? " [ENCRYPTED]" : "") . "...");

        try {
            $result = $backupService->createBackup($type, $name, $encrypt, $key);

            $this->info("✓ Backup created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['Archive Name', $result['archive_name']],
                    ['Archive Path', $result['archive_path']],
                    ['Encrypted', $result['encrypted'] ? 'YES (AES-256-CBC)' : 'No'],
                    ['Size', number_format($result['file_size_bytes'] / 1024, 2) . ' KB'],
                    ['SHA256', $result['sha256']],
                    ['Tables Backed Up', count($result['manifest']['tables'])],
                    ['Media Files Backed Up', $result['manifest']['media_files_count']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
