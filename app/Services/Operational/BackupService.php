<?php

declare(strict_types=1);

namespace App\Services\Operational;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupService
{
    public const ENC_MAGIC = 'NA_ENC_V2';
    public const ENC_MAGIC_V1 = 'NA_ENC_V1';

    public function __construct(
        protected ?AuditLoggerService $auditLogger = null
    ) {
        $this->backupDir = storage_path('app/backups');
        if (!File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a compressed backup archive (database, media, or both), with optional AES-256-CBC encryption.
     */
    public function createBackup(
        string $type = 'all',
        ?string $customName = null,
        bool $encrypt = false,
        ?string $encryptionKey = null
    ): array {
        if (!in_array($type, ['all', 'db', 'media'], true)) {
            throw new \InvalidArgumentException("Invalid backup type: {$type}. Expected 'all', 'db', or 'media'.");
        }

        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException("ZipArchive PHP extension is required for enterprise backups.");
        }

        $timestamp = date('Ymd_His');
        $baseFileName = $customName ?: "laijau_backup_{$type}_{$timestamp}.zip";
        $archivePath = "{$this->backupDir}/{$baseFileName}";

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create zip archive at: {$archivePath}");
        }

        $manifest = [
            'backup_name' => $baseFileName,
            'created_at' => now()->toIso8601String(),
            'type' => $type,
            'app_version' => '2.0.0-enterprise',
            'database_driver' => DB::connection()->getDriverName(),
            'encrypted' => $encrypt,
            'tables' => [],
            'media_files_count' => 0,
        ];

        // 1. Export Database Tables
        if (in_array($type, ['all', 'db'], true)) {
            $tables = $this->getAllTableNames();

            foreach ($tables as $table) {
                // Skip transient / cache / session tables
                if (in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches'], true)) {
                    continue;
                }

                $rows = DB::table($table)->get()->map(fn ($r) => (array)$r)->all();
                $rowCount = count($rows);
                $manifest['tables'][$table] = $rowCount;

                $jsonContent = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $zip->addFromString("database/{$table}.json", $jsonContent ?: '[]');
            }
        }

        // 2. Export Media Files
        if (in_array($type, ['all', 'media'], true)) {
            $mediaPath = storage_path('app/public');
            if (File::exists($mediaPath)) {
                $files = File::allFiles($mediaPath);
                $manifest['media_files_count'] = count($files);

                foreach ($files as $file) {
                    $relativePath = 'media/' . $file->getRelativePathname();
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
        }

        // 3. Write manifest into Zip
        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT);
        $zip->addFromString('manifest.json', $manifestJson ?: '{}');
        $zip->close();

        $rawFileSize = (int)File::size($archivePath);
        $sha256 = hash_file('sha256', $archivePath);
        $manifest['sha256'] = $sha256;
        $manifest['file_size_bytes'] = $rawFileSize;

        $finalPath = $archivePath;
        $finalName = $baseFileName;
        $finalSize = $rawFileSize;

        // 4. Encrypt Archive if requested (AES-256-GCM Authenticated Encryption)
        if ($encrypt) {
            $key = hash_hkdf('sha256', (string) ($encryptionKey ?: config('app.key')), 32, 'laijau-backup-v2');
            $iv = random_bytes(12);
            $zipBytes = File::get($archivePath);
            $tag = '';
            $encryptedBytes = openssl_encrypt($zipBytes, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);

            if ($encryptedBytes === false || strlen($tag) !== 16) {
                throw new \RuntimeException("Authenticated encryption failed during backup archive creation.");
            }

            $payload = self::ENC_MAGIC . $iv . $tag . $encryptedBytes;
            $encryptedPath = "{$archivePath}.enc";
            File::put($encryptedPath, $payload);
            File::delete($archivePath); // Delete unencrypted file

            $finalPath = $encryptedPath;
            $finalName = "{$baseFileName}.enc";
            $finalSize = (int)File::size($encryptedPath);
            $sha256 = hash_file('sha256', $encryptedPath);
            $manifest['sha256'] = $sha256;
            $manifest['file_size_bytes'] = $finalSize;
        }

        $this->auditLogger?->backupEvent('backup_created', $finalName, $finalSize, [
            'type' => $type,
            'sha256' => $sha256,
            'encrypted' => $encrypt,
            'table_count' => count($manifest['tables']),
        ]);

        return [
            'status' => 'success',
            'archive_path' => $finalPath,
            'archive_name' => $finalName,
            'file_size_bytes' => $finalSize,
            'sha256' => $sha256,
            'encrypted' => $encrypt,
            'manifest' => $manifest,
        ];
    }

    /**
     * Restore a backup archive into the target database connection.
     */
    public function restoreBackup(
        string $archivePath,
        ?string $targetConnection = null,
        bool $dryRun = false,
        ?string $decryptionKey = null
    ): array {
        if (!File::exists($archivePath)) {
            throw new \InvalidArgumentException("Backup file not found at: {$archivePath}");
        }

        $pathToOpen = $archivePath;
        $isEncrypted = false;
        $tempZip = null;

        // Check if archive is encrypted
        $fileHeader = File::get($archivePath, true);
        if (str_starts_with($fileHeader, self::ENC_MAGIC) || str_starts_with($fileHeader, self::ENC_MAGIC_V1) || str_ends_with($archivePath, '.enc')) {
            $isEncrypted = true;
            $rawPayload = File::get($archivePath);

            if (str_starts_with($rawPayload, self::ENC_MAGIC)) {
                // V2 Authenticated Format: NA_ENC_V2 + 12-byte IV + 16-byte Auth Tag + Ciphertext
                $magicLen = strlen(self::ENC_MAGIC);
                if (strlen($rawPayload) < $magicLen + 12 + 16) {
                    throw new \RuntimeException("Corrupt encrypted archive: payload too short.");
                }

                $iv = substr($rawPayload, $magicLen, 12);
                $tag = substr($rawPayload, $magicLen + 12, 16);
                $ciphertext = substr($rawPayload, $magicLen + 12 + 16);

                $key = hash_hkdf('sha256', (string) ($decryptionKey ?: config('app.key')), 32, 'laijau-backup-v2');
                $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

                if ($decrypted === false || !str_starts_with($decrypted, "PK\x03\x04")) {
                    throw new \RuntimeException("Decryption failed: invalid encryption key, modified authentication tag, or corrupted archive.");
                }
            } elseif (str_starts_with($rawPayload, self::ENC_MAGIC_V1)) {
                // V1 Legacy Format: NA_ENC_V1 + 16-byte IV + CBC Ciphertext
                $magicLen = strlen(self::ENC_MAGIC_V1);
                if (strlen($rawPayload) < $magicLen + 16) {
                    throw new \RuntimeException("Corrupt encrypted archive: payload too short for legacy format.");
                }

                $iv = substr($rawPayload, $magicLen, 16);
                $ciphertext = substr($rawPayload, $magicLen + 16);

                $key = hash('sha256', (string) ($decryptionKey ?: config('app.key')), true);
                $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

                if ($decrypted === false || !str_starts_with($decrypted, "PK\x03\x04")) {
                    throw new \RuntimeException("Decryption failed: invalid encryption key or corrupted archive.");
                }

                $this->auditLogger?->warning('Restoring legacy unauthenticated backup archive (AES-256-CBC). Please re-encrypt with authenticated AES-256-GCM.');
            } else {
                throw new \RuntimeException("Corrupt encrypted archive: missing valid encryption magic header.");
            }

            $tempZip = "{$this->backupDir}/tmp_restore_" . uniqid() . ".zip";
            File::put($tempZip, $decrypted);
            $pathToOpen = $tempZip;
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($pathToOpen) !== true) {
                if ($isEncrypted) {
                    throw new \RuntimeException("Decryption failed: invalid encryption key or corrupted archive.");
                }
                throw new \RuntimeException("Could not read backup zip archive: {$archivePath}");
            }

            $manifestRaw = $zip->getFromName('manifest.json');
            if ($manifestRaw === false) {
                $zip->close();
                throw new \RuntimeException("Corrupted backup: missing manifest.json in archive.");
            }

            $manifest = json_decode($manifestRaw, true);
            if (!is_array($manifest)) {
                $zip->close();
                throw new \RuntimeException("Corrupted backup: invalid manifest.json format.");
            }

            if ($dryRun) {
                $zip->close();
                return [
                    'status' => 'dry_run_verified',
                    'manifest' => $manifest,
                    'encrypted' => $isEncrypted,
                    'archive_sha256' => hash_file('sha256', $archivePath),
                ];
            }

            $conn = DB::connection($targetConnection);
            $driver = $conn->getDriverName();

            // Disable foreign keys during restoration
            $this->setForeignKeys($conn, false);

            $restoredTables = [];
            $totalRowsRestored = 0;

            try {
                $expectedTables = $manifest['tables'] ?? [];

                foreach ($expectedTables as $table => $expectedRows) {
                    $tableContent = $zip->getFromName("database/{$table}.json");
                    if ($tableContent === false) {
                        continue;
                    }

                    $rows = json_decode($tableContent, true);
                    if (!is_array($rows)) {
                        continue;
                    }

                    // Clean existing rows
                    if (Schema::connection($targetConnection)->hasTable($table)) {
                        $conn->table($table)->truncate();
                    } else {
                        continue;
                    }

                    // Batch insert rows
                    $chunks = array_chunk($rows, 100);
                    foreach ($chunks as $chunk) {
                        if (!empty($chunk)) {
                            $conn->table($table)->insert($chunk);
                        }
                    }

                    $actualCount = $conn->table($table)->count();
                    $restoredTables[$table] = [
                        'expected' => $expectedRows,
                        'actual' => $actualCount,
                        'matched' => ($actualCount === $expectedRows),
                    ];
                    $totalRowsRestored += $actualCount;
                }

                // Restore media files if present
                if (in_array($manifest['type'], ['all', 'media'], true)) {
                    $mediaTarget = storage_path('app/public');
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = $zip->getNameIndex($i);
                        if (str_starts_with($entryName, 'media/')) {
                            $relative = substr($entryName, 6);
                            if (!empty($relative) && !str_ends_with($relative, '/')) {
                                $targetFile = "{$mediaTarget}/{$relative}";
                                $parent = dirname($targetFile);
                                if (!File::exists($parent)) {
                                    File::makeDirectory($parent, 0755, true);
                                }
                                File::put($targetFile, $zip->getFromIndex($i));
                            }
                        }
                    }
                }
            } finally {
                // Re-enable foreign keys
                $this->setForeignKeys($conn, true);
                $zip->close();
            }

            $this->auditLogger?->backupEvent('backup_restored', basename($archivePath), (int)File::size($archivePath), [
                'tables_restored' => count($restoredTables),
                'rows_restored' => $totalRowsRestored,
                'encrypted' => $isEncrypted,
            ]);

            return [
                'status' => 'success',
                'manifest' => $manifest,
                'encrypted' => $isEncrypted,
                'total_rows_restored' => $totalRowsRestored,
                'tables' => $restoredTables,
            ];
        } finally {
            if ($tempZip && File::exists($tempZip)) {
                File::delete($tempZip);
            }
        }
    }

    /**
     * Get list of all tables in the current connection.
     */
    protected function getAllTableNames(): array
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            return $conn->table('sqlite_master')
                ->where('type', 'table')
                ->where('name', 'not like', 'sqlite_%')
                ->pluck('name')
                ->all();
        }

        if ($driver === 'mysql') {
            return collect($conn->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
                ->map(function ($row) {
                    $arr = (array)$row;
                    return reset($arr);
                })
                ->filter()
                ->values()
                ->all();
        }

        return Schema::getTableListing();
    }

    /**
     * Toggle foreign key constraints across SQLite, MySQL, and PostgreSQL.
     */
    protected function setForeignKeys($connection, bool $enabled): void
    {
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $connection->statement($enabled ? 'PRAGMA foreign_keys = ON;' : 'PRAGMA foreign_keys = OFF;');
        } elseif ($driver === 'mysql') {
            $connection->statement($enabled ? 'SET FOREIGN_KEY_CHECKS = 1;' : 'SET FOREIGN_KEY_CHECKS = 0;');
        } elseif ($driver === 'pgsql') {
            $connection->statement($enabled ? "SET session_replication_role = 'origin';" : "SET session_replication_role = 'replica';");
        }
    }
}
