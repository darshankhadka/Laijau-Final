<?php

namespace Tests\Feature;

use App\Services\Operational\BackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupEncryptionTest extends TestCase
{
    protected BackupService $backupService;
    protected array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupService = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
        parent::tearDown();
    }

    /**
     * Helper to create a minimal valid zip file for testing.
     */
    protected function createSampleZip(): string
    {
        $dir = storage_path('app/backups');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $zipPath = "{$dir}/test_sample_" . uniqid() . ".zip";
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode(['tables' => [], 'type' => 'db']));
        $zip->close();

        $this->tempFiles[] = $zipPath;
        return $zipPath;
    }

    /**
     * Test 1: Successful authenticated backup creation and dry-run restore using AES-256-GCM.
     */
    public function test_authenticated_backup_encrypt_and_decrypt_success(): void
    {
        $key = 'StrongSecretKeyForBackupTest123!@#';
        $name = 'gcm_test_' . uniqid() . '.zip';

        $result = $this->backupService->createBackup(
            type: 'db',
            customName: $name,
            encrypt: true,
            encryptionKey: $key
        );

        $this->assertEquals('success', $result['status']);
        $this->assertTrue($result['encrypted']);
        $this->assertStringEndsWith('.enc', $result['archive_path']);
        $this->tempFiles[] = $result['archive_path'];

        $payload = File::get($result['archive_path']);
        $this->assertStringStartsWith(BackupService::ENC_MAGIC, $payload);

        // Verify restore succeeds
        $restore = $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: $key
        );

        $this->assertEquals('dry_run_verified', $restore['status']);
        $this->assertTrue($restore['encrypted']);
    }

    /**
     * Test 2: Decryption failure when incorrect key is provided.
     */
    public function test_authenticated_backup_fails_with_wrong_key(): void
    {
        $key = 'CorrectKey123';
        $wrongKey = 'WrongKey456';
        $name = 'wrong_key_test_' . uniqid() . '.zip';

        $result = $this->backupService->createBackup(
            type: 'db',
            customName: $name,
            encrypt: true,
            encryptionKey: $key
        );
        $this->tempFiles[] = $result['archive_path'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: $wrongKey
        );
    }

    /**
     * Test 3: Tampered ciphertext causes authenticated decryption rejection.
     */
    public function test_tampered_ciphertext_fails_integrity_check(): void
    {
        $key = 'IntegrityKey123';
        $name = 'tamper_cipher_' . uniqid() . '.zip';

        $result = $this->backupService->createBackup(
            type: 'db',
            customName: $name,
            encrypt: true,
            encryptionKey: $key
        );
        $this->tempFiles[] = $result['archive_path'];

        $payload = File::get($result['archive_path']);
        // Flip one byte near the end (in the ciphertext)
        $tampered = substr($payload, 0, -5) . chr(ord(substr($payload, -5, 1)) ^ 0xFF) . substr($payload, -4);
        File::put($result['archive_path'], $tampered);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: $key
        );
    }

    /**
     * Test 4: Tampered authentication tag causes immediate failure.
     */
    public function test_tampered_auth_tag_fails_verification(): void
    {
        $key = 'TagKey123';
        $name = 'tamper_tag_' . uniqid() . '.zip';

        $result = $this->backupService->createBackup(
            type: 'db',
            customName: $name,
            encrypt: true,
            encryptionKey: $key
        );
        $this->tempFiles[] = $result['archive_path'];

        $payload = File::get($result['archive_path']);
        $magicLen = strlen(BackupService::ENC_MAGIC);
        // Auth tag is located at offset $magicLen + 12 (16 bytes long)
        $tagOffset = $magicLen + 12;
        $corruptedTag = substr($payload, 0, $tagOffset) . str_repeat("\x00", 16) . substr($payload, $tagOffset + 16);
        File::put($result['archive_path'], $corruptedTag);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->backupService->restoreBackup(
            archivePath: $result['archive_path'],
            dryRun: true,
            decryptionKey: $key
        );
    }

    /**
     * Test 5: Backward compatibility: successfully restores legacy NA_ENC_V1 archive.
     */
    public function test_legacy_v1_backup_backward_compatibility(): void
    {
        $zipPath = $this->createSampleZip();
        $key = 'LegacyKey123';

        // Manually craft a V1 AES-256-CBC backup payload
        $derivedKey = hash('sha256', $key, true);
        $iv = random_bytes(16);
        $zipBytes = File::get($zipPath);
        $ciphertext = openssl_encrypt($zipBytes, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);

        $v1Payload = BackupService::ENC_MAGIC_V1 . $iv . $ciphertext;
        $legacyEncPath = storage_path('app/backups/legacy_test_' . uniqid() . '.zip.enc');
        File::put($legacyEncPath, $v1Payload);
        $this->tempFiles[] = $legacyEncPath;

        $restore = $this->backupService->restoreBackup(
            archivePath: $legacyEncPath,
            dryRun: true,
            decryptionKey: $key
        );

        $this->assertEquals('dry_run_verified', $restore['status']);
        $this->assertTrue($restore['encrypted']);
    }

    /**
     * Test 6: Empty or invalid encrypted file failure.
     */
    public function test_empty_or_truncated_file_fails_cleanly(): void
    {
        $truncatedPath = storage_path('app/backups/truncated_' . uniqid() . '.zip.enc');
        File::put($truncatedPath, 'NA_ENC_V2_TOO_SHORT');
        $this->tempFiles[] = $truncatedPath;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Corrupt encrypted archive');

        $this->backupService->restoreBackup(
            archivePath: $truncatedPath,
            dryRun: true,
            decryptionKey: 'some_key'
        );
    }
}
