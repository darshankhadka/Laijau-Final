<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageOptimizerService
{
    /**
     * Derivative configuration parameters.
     */
    public const THUMBNAIL_MAX_WIDTH = 200;
    public const THUMBNAIL_MAX_HEIGHT = 200;
    public const THUMBNAIL_QUALITY = 80;

    public const CARD_MAX_WIDTH = 400;
    public const CARD_MAX_HEIGHT = 400;
    public const CARD_QUALITY = 82;

    public const LARGE_MAX_WIDTH = 1000;
    public const LARGE_MAX_HEIGHT = 1000;
    public const LARGE_QUALITY = 84;

    public const DEFAULT_MAX_WIDTH = 1800;
    public const DEFAULT_MAX_HEIGHT = 1800;
    public const DEFAULT_QUALITY = 82;

    /**
     * Optimize an image file given its relative storage path and generate all standard derivatives.
     *
     * @param string $relativePath
     * @param string $disk
     * @param int $maxWidth
     * @param int $maxHeight
     * @param int $quality
     * @param bool $convertToWebp
     * @return string|null New relative path if converted/optimized, or original if failed
     */
    public static function optimizeStoragePath(
        string $relativePath,
        string $disk = 'public',
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT,
        int $quality = self::DEFAULT_QUALITY,
        bool $convertToWebp = true
    ): ?string {
        $storage = Storage::disk($disk);

        // Normalize path: strip domain or /storage/ prefix if present
        $clean = self::normalizePath($relativePath);

        if (!$storage->exists($clean)) {
            $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $clean);
            if ($storage->exists($webpPath)) {
                $clean = $webpPath;
            } else {
                return $relativePath;
            }
        }

        $fullPath = $storage->path($clean);
        $optimizedPath = self::optimizeFile($fullPath, $maxWidth, $maxHeight, $quality, $convertToWebp);

        $resultPath = $clean;
        if ($optimizedPath) {
            $baseDir = $storage->path('');
            if (str_starts_with($optimizedPath, $baseDir)) {
                $resultPath = ltrim(substr($optimizedPath, strlen($baseDir)), '/\\');
            }
        }

        // Generate derivatives (thumbnail, card, large)
        self::generateDerivativesForPath($resultPath, $disk);

        return $resultPath;
    }

    /**
     * Generate all standard responsive derivatives (thumbnail: 200px, card: 400px, large: 1000px)
     * for a given relative image path.
     *
     * @param string $relativePath
     * @param string $disk
     * @param bool $force
     * @return array<string, string> Array of relative derivative paths keyed by size name
     */
    public static function generateDerivativesForPath(
        string $relativePath,
        string $disk = 'public',
        bool $force = false
    ): array {
        $clean = self::normalizePath($relativePath);
        if (empty($clean)) {
            return [];
        }

        $storage = Storage::disk($disk);
        $baseStoragePath = $storage->path('');

        // Find best source file
        $sourceFullPath = self::resolveBestSourceFullPath($clean, $baseStoragePath);
        if (!$sourceFullPath || !file_exists($sourceFullPath) || !is_file($sourceFullPath)) {
            return [];
        }

        $pathInfo = pathinfo($clean);
        $filename = $pathInfo['filename'];
        $dirname = $pathInfo['dirname'] === '.' ? 'product' : $pathInfo['dirname'];

        // Normalize folder: remove existing derivative subfolder if part of dirname
        $folderParts = explode('/', str_replace('\\', '/', $dirname));
        $cleanedParts = array_filter($folderParts, fn($part) => !in_array($part, ['thumbnail', 'card', 'large', 'original']));
        $targetBaseFolder = !empty($cleanedParts) ? implode('/', $cleanedParts) : 'product';

        // Load master source image into GD once
        $srcImage = self::loadGdImage($sourceFullPath);
        if (!$srcImage) {
            return [];
        }

        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Preserve master copy in original/ if not already preserved
        $origRel = "{$targetBaseFolder}/original/{$filename}." . pathinfo($sourceFullPath, PATHINFO_EXTENSION);
        $origFullPath = $baseStoragePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $origRel);
        if (!file_exists($origFullPath)) {
            @mkdir(dirname($origFullPath), 0775, true);
            @copy($sourceFullPath, $origFullPath);
        }

        $derivatives = [
            'large' => [
                'rel' => "{$targetBaseFolder}/large/{$filename}.webp",
                'width' => self::LARGE_MAX_WIDTH,
                'height' => self::LARGE_MAX_HEIGHT,
                'quality' => self::LARGE_QUALITY,
            ],
            'card' => [
                'rel' => "{$targetBaseFolder}/card/{$filename}.webp",
                'width' => self::CARD_MAX_WIDTH,
                'height' => self::CARD_MAX_HEIGHT,
                'quality' => self::CARD_QUALITY,
            ],
            'thumbnail' => [
                'rel' => "{$targetBaseFolder}/thumbnail/{$filename}.webp",
                'width' => self::THUMBNAIL_MAX_WIDTH,
                'height' => self::THUMBNAIL_MAX_HEIGHT,
                'quality' => self::THUMBNAIL_QUALITY,
            ],
        ];

        $results = [];

        foreach ($derivatives as $sizeKey => $spec) {
            $destFullPath = $baseStoragePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $spec['rel']);
            $destDir = dirname($destFullPath);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }

            if (!$force && file_exists($destFullPath) && filesize($destFullPath) > 0) {
                $info = @getimagesize($destFullPath);
                if ($info && $info[0] > 0) {
                    $results[$sizeKey] = $spec['rel'];
                    continue;
                }
            }

            // Calculate proportional scale dimensions
            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $spec['width'] || $origHeight > $spec['height']) {
                $ratio = min($spec['width'] / $origWidth, $spec['height'] / $origHeight);
                $newWidth = (int) max(1, round($origWidth * $ratio));
                $newHeight = (int) max(1, round($origHeight * $ratio));
            }

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);

            imagecopyresampled(
                $dstImage,
                $srcImage,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            );

            $success = false;
            if (function_exists('imagewebp')) {
                $success = imagewebp($dstImage, $destFullPath, $spec['quality']);
            }
            imagedestroy($dstImage);

            if ($success && file_exists($destFullPath)) {
                $results[$sizeKey] = $spec['rel'];
            }
        }

        imagedestroy($srcImage);

        return $results;
    }

    /**
     * Load an image file into a GD resource safely.
     */
    public static function loadGdImage(string $absolutePath)
    {
        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            return null;
        }

        @ini_set('memory_limit', '256M');

        $imageInfo = @getimagesize($absolutePath);
        if (!$imageInfo) {
            return null;
        }

        [$width, $height, $type] = $imageInfo;
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = @imagecreatefromjpeg($absolutePath);
                if ($srcImage && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($absolutePath);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $srcImage = imagerotate($srcImage, 180, 0);
                                break;
                            case 6:
                                $srcImage = imagerotate($srcImage, -90, 0);
                                break;
                            case 8:
                                $srcImage = imagerotate($srcImage, 90, 0);
                                break;
                        }
                    }
                }
                break;

            case IMAGETYPE_PNG:
                $srcImage = @imagecreatefrompng($absolutePath);
                break;

            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = @imagecreatefromwebp($absolutePath);
                }
                break;

            case IMAGETYPE_GIF:
                $srcImage = @imagecreatefromgif($absolutePath);
                break;
        }

        return $srcImage;
    }

    /**
     * Resolve the highest resolution available source file on disk.
     */
    public static function resolveBestSourceFullPath(string $cleanRelPath, string $baseStoragePath): ?string
    {
        $pathInfo = pathinfo($cleanRelPath);
        $filename = $pathInfo['filename'];
        $dirname = $pathInfo['dirname'];

        // Normalize folder
        $folderParts = explode('/', str_replace('\\', '/', $dirname));
        $cleanedParts = array_filter($folderParts, fn($part) => !in_array($part, ['thumbnail', 'card', 'large', 'original']));
        $targetBaseFolder = !empty($cleanedParts) ? implode('/', $cleanedParts) : 'product';

        $candidateLocations = [
            "{$targetBaseFolder}/original",
            $targetBaseFolder,
            "{$targetBaseFolder}/large",
            'product',
            'products',
            'product/original',
            'product/large',
            $dirname,
        ];

        $bestFile = null;
        $bestPixels = 0;

        foreach ($candidateLocations as $dir) {
            foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                $candidate = $baseStoragePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, "{$dir}/{$filename}.{$ext}");
                if (file_exists($candidate) && is_file($candidate)) {
                    $info = @getimagesize($candidate);
                    if ($info) {
                        $pixels = $info[0] * $info[1];
                        if ($pixels > $bestPixels) {
                            $bestPixels = $pixels;
                            $bestFile = $candidate;
                        }
                    }
                }
            }
        }

        if ($bestFile) {
            return $bestFile;
        }

        $directPath = $baseStoragePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRelPath);
        if (file_exists($directPath) && is_file($directPath)) {
            return $directPath;
        }

        return null;
    }

    /**
     * Clean and normalize storage path string.
     */
    public static function normalizePath(?string $path): string
    {
        if (empty($path)) {
            return '';
        }

        $clean = trim($path);
        if (str_contains($clean, '/storage/')) {
            $clean = substr($clean, strpos($clean, '/storage/') + strlen('/storage/'));
        } elseif (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/'));
        }

        return ltrim($clean, '/\\');
    }

    /**
     * Optimize an image file at an absolute file path.
     *
     * @param string $absolutePath
     * @param int $maxWidth
     * @param int $maxHeight
     * @param int $quality
     * @param bool $convertToWebp
     * @return string|null The absolute path of the optimized file
     */
    public static function optimizeFile(
        string $absolutePath,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT,
        int $quality = self::DEFAULT_QUALITY,
        bool $convertToWebp = true
    ): ?string {
        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            return null;
        }

        $mime = @mime_content_type($absolutePath);
        if (!$mime || !str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
            return $absolutePath;
        }

        @ini_set('memory_limit', '256M');

        $imageInfo = @getimagesize($absolutePath);
        if (!$imageInfo) {
            return $absolutePath;
        }

        [$width, $height, $type] = $imageInfo;

        $srcImage = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = @imagecreatefromjpeg($absolutePath);
                if ($srcImage && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($absolutePath);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $srcImage = imagerotate($srcImage, 180, 0);
                                break;
                            case 6:
                                $srcImage = imagerotate($srcImage, -90, 0);
                                $temp = $width;
                                $width = $height;
                                $height = $temp;
                                break;
                            case 8:
                                $srcImage = imagerotate($srcImage, 90, 0);
                                $temp = $width;
                                $width = $height;
                                $height = $temp;
                                break;
                        }
                    }
                }
                break;

            case IMAGETYPE_PNG:
                $srcImage = @imagecreatefrompng($absolutePath);
                break;

            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = @imagecreatefromwebp($absolutePath);
                }
                break;

            case IMAGETYPE_GIF:
                $srcImage = @imagecreatefromgif($absolutePath);
                break;

            default:
                return $absolutePath;
        }

        if (!$srcImage) {
            return $absolutePath;
        }

        $newWidth = $width;
        $newHeight = $height;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int) max(1, round($width * $ratio));
            $newHeight = (int) max(1, round($height * $ratio));
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled(
            $dstImage,
            $srcImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        $pathInfo = pathinfo($absolutePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];

        $targetPath = $absolutePath;
        $success = false;

        if ($convertToWebp && function_exists('imagewebp')) {
            $targetPath = $directory . DIRECTORY_SEPARATOR . $filename . '.webp';
            $success = imagewebp($dstImage, $targetPath, $quality);

            if ($success && $targetPath !== $absolutePath && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        } elseif ($type === IMAGETYPE_JPEG) {
            $success = imagejpeg($dstImage, $targetPath, $quality);
        } elseif ($type === IMAGETYPE_PNG) {
            $pngCompression = (int) round((100 - $quality) / 10);
            $pngCompression = max(0, min(9, $pngCompression));
            $success = imagepng($dstImage, $targetPath, $pngCompression);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
            $success = imagewebp($dstImage, $targetPath, $quality);
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $success ? $targetPath : $absolutePath;
    }
}
