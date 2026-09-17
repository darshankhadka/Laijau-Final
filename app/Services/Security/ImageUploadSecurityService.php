<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImageUploadSecurityService
{
    /**
     * Whitelisted image extensions.
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    /**
     * Whitelisted MIME types.
     */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ];

    /**
     * Prohibited executable and dangerous extensions.
     */
    public const BLOCKED_EXTENSIONS = [
        'php',
        'php3',
        'php4',
        'php5',
        'phtml',
        'phar',
        'inc',
        'sh',
        'bash',
        'bin',
        'exe',
        'cgi',
        'pl',
        'py',
        'html',
        'htm',
        'js',
        'jsp',
        'asp',
        'aspx',
        'svg',
        'xml',
    ];

    /**
     * Maximum allowed image dimension in pixels (width or height).
     */
    public const MAX_DIMENSION = 5000;

    /**
     * Maximum allowed file size in kilobytes (10 MB).
     */
    public const MAX_FILE_SIZE_KB = 10240;

    /**
     * Validate an uploaded file strictly for genuine image integrity and security.
     * Throws InvalidArgumentException on failure.
     */
    public static function validateImageFile(UploadedFile $file): void
    {
        // 1. Check basic upload validity
        if (!$file->isValid()) {
            throw new InvalidArgumentException("The uploaded file is corrupt or was not received completely.");
        }

        // 2. Check file size
        $sizeKb = $file->getSize() / 1024;
        if ($sizeKb > self::MAX_FILE_SIZE_KB) {
            throw new InvalidArgumentException("Image file exceeds the maximum allowed size of 10 MB.");
        }

        // 3. Validate client extension
        $clientExt = strtolower($file->getClientOriginalExtension());
        if (in_array($clientExt, self::BLOCKED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("Disallowed file extension. Executable and script files cannot be uploaded.");
        }
        if (!in_array($clientExt, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException("Invalid image format. Allowed formats: " . implode(', ', self::ALLOWED_EXTENSIONS));
        }

        // 4. Validate MIME type detected by PHP finfo
        $realMime = $file->getMimeType();
        if (!in_array($realMime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException("Invalid image MIME type ({$realMime}). The file content does not match allowed image formats.");
        }

        // 5. Inspect binary image contents via getimagesize()
        $imageInfo = @getimagesize($file->getRealPath());
        if ($imageInfo === false) {
            throw new InvalidArgumentException("The uploaded file does not contain valid binary image data.");
        }

        [$width, $height] = $imageInfo;
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException("Invalid image dimensions ({$width}x{$height}).");
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException("Image dimensions ({$width}x{$height}) exceed maximum allowed dimension of " . self::MAX_DIMENSION . "px.");
        }
    }

    /**
     * Generate a cryptographically random, collision-free, safe storage filename.
     * Prevents path traversal and client-controlled filename injection.
     */
    public static function generateSafeFilename(UploadedFile $file, string $prefix = 'img'): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $ext = 'jpg';
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        $randomHash = Str::random(24);
        $timestamp = date('Ymd_His');

        return "{$safePrefix}_{$timestamp}_{$randomHash}.{$ext}";
    }
}
