<?php

namespace App\Helpers;

class StorefrontHelper
{
    /**
     * Resolves any product or media asset into a valid, canonical URL.
     * Enforces photo-backed rule: returns empty string if no valid photo exists (no placeholders).
     */
    public static function getImageUrl($path = null): string
    {
        if (empty($path)) {
            return '';
        }

        $rawPath = is_string($path) ? $path : static::extractImagePath($path);
        if (empty($rawPath)) {
            return '';
        }

        $trimmed = trim($rawPath);
        if ($trimmed === '' || $trimmed === 'null' || $trimmed === 'undefined' || $trimmed === '[object Object]' || str_contains(strtolower($trimmed), 'placeholder')) {
            return '';
        }

        // If path contains /storage/, normalize to root-relative /storage/...
        if (str_contains($trimmed, '/storage/')) {
            $storageIdx = strpos($trimmed, '/storage/');
            $rawSubpath = substr($trimmed, $storageIdx + strlen('/storage/'));
            $cleanSubpath = preg_replace('#^(\/?storage\/)+#', '', $rawSubpath);
            $cleanSubpath = ltrim($cleanSubpath, '/');
            return '/storage/' . $cleanSubpath;
        }

        // 1. Local public assets (e.g. /logo.png, /images/...)
        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        // 2. Absolute third-party URLs (e.g. CDN or external images)
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        // 3. Direct public asset folders (e.g. images/, assets/, build/, favicon, logos)
        if (str_starts_with($trimmed, 'images/') || str_starts_with($trimmed, 'assets/') || str_starts_with($trimmed, 'build/') || str_starts_with($trimmed, 'icons/')) {
            return '/' . $trimmed;
        }

        // 4. Clean relative or storage-prefixed paths
        $cleanSubpath = preg_replace('#^(\/?storage\/)+#', '', $trimmed);
        $cleanSubpath = ltrim($cleanSubpath, '/');

        if (empty($cleanSubpath) || $cleanSubpath === 'null' || $cleanSubpath === 'undefined') {
            return '';
        }

        return '/storage/' . $cleanSubpath;
    }

    /**
     * Extracts an image path from string or array/object candidate.
     */
    public static function extractImagePath($item): ?string
    {
        if (empty($item)) {
            return null;
        }

        if (is_string($item)) {
            $t = trim($item);
            return (!empty($t) && $t !== 'null' && $t !== 'undefined' && $t !== '[object Object]') ? $t : null;
        }

        if (is_array($item)) {
            $candidate = $item['url'] ?? $item['path'] ?? $item['image_path'] ?? $item['src'] ?? $item['image'] ?? null;
            if (is_string($candidate)) {
                $t = trim($candidate);
                return (!empty($t) && $t !== 'null' && $t !== 'undefined' && $t !== '[object Object]') ? $t : null;
            }
        }

        if (is_object($item)) {
            $candidate = $item->url ?? $item->path ?? $item->image_path ?? $item->src ?? $item->image ?? null;
            if (is_string($candidate)) {
                $t = trim($candidate);
                return (!empty($t) && $t !== 'null' && $t !== 'undefined' && $t !== '[object Object]') ? $t : null;
            }
        }

        return null;
    }

    /**
     * Resolve derivative URL for a product or image path.
     * Supported sizes: 'thumbnail' (200px), 'card' (400px), 'large' (1000px), 'original'.
     */
    public static function getProductDerivativeUrl($item, string $size = 'card'): string
    {
        if (empty($item)) {
            return '';
        }

        $rawPath = is_string($item) ? $item : static::extractImagePath($item);
        if (empty($rawPath)) {
            if (is_object($item) || is_array($item)) {
                $rawPath = static::extractImagePath(is_object($item) ? ($item->featured_image ?? null) : ($item['featured_image'] ?? null));
                if (empty($rawPath)) {
                    $images = is_object($item) ? ($item->images ?? null) : ($item['images'] ?? null);
                    if (is_array($images) && count($images) > 0) {
                        $rawPath = static::extractImagePath($images[0]);
                    }
                }
            }
        }

        if (empty($rawPath)) {
            return '';
        }

        $clean = trim($rawPath);
        if ($clean === '' || $clean === 'null' || $clean === 'undefined' || $clean === '[object Object]' || str_contains(strtolower($clean), 'placeholder')) {
            return '';
        }

        // External or public assets fallback directly
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            return $clean;
        }
        if (str_starts_with($clean, '/') && !str_starts_with($clean, '/storage/')) {
            return $clean;
        }

        // Normalize subpath
        $subpath = $clean;
        if (str_contains($subpath, '/storage/')) {
            $subpath = substr($subpath, strpos($subpath, '/storage/') + strlen('/storage/'));
        } elseif (str_starts_with($subpath, 'storage/')) {
            $subpath = substr($subpath, strlen('storage/'));
        }
        $subpath = ltrim($subpath, '/\\');

        $pathInfo = pathinfo($subpath);
        $filename = $pathInfo['filename'];
        $dirname = $pathInfo['dirname'] === '.' ? 'product' : $pathInfo['dirname'];

        // Normalize folder by stripping any derivative directory name
        $folderParts = explode('/', str_replace('\\', '/', $dirname));
        $cleanedParts = array_filter($folderParts, fn($part) => !in_array($part, ['thumbnail', 'card', 'large', 'original']));
        $targetBaseFolder = !empty($cleanedParts) ? implode('/', $cleanedParts) : 'product';

        $validSizes = ['thumbnail', 'card', 'large'];
        $requestedSize = in_array($size, $validSizes) ? $size : 'card';

        // 1. Check if the exact requested derivative exists
        $derivativeRelative = "{$targetBaseFolder}/{$requestedSize}/{$filename}.webp";
        $derivativeDiskPath = public_path("storage/{$derivativeRelative}");
        if (file_exists($derivativeDiskPath)) {
            return "/storage/{$derivativeRelative}";
        }

        // Also check storage/app/public directly if symlink isn't resolved yet
        $storageDiskPath = storage_path("app/public/{$derivativeRelative}");
        if (file_exists($storageDiskPath)) {
            return "/storage/{$derivativeRelative}";
        }

        // 2. Fallback to other available derivatives in order of proximity
        $fallbackOrder = match ($requestedSize) {
            'thumbnail' => ['card', 'large'],
            'card' => ['thumbnail', 'large'],
            'large' => ['card', 'thumbnail'],
            default => ['card', 'thumbnail', 'large'],
        };

        foreach ($fallbackOrder as $altSize) {
            $altRelative = "{$targetBaseFolder}/{$altSize}/{$filename}.webp";
            if (file_exists(public_path("storage/{$altRelative}")) || file_exists(storage_path("app/public/{$altRelative}"))) {
                return "/storage/{$altRelative}";
            }
        }

        // 3. Fallback to canonical original image
        return static::getImageUrl($rawPath);
    }

    /**
     * Generate responsive srcset for an image or product.
     */
    public static function getProductSrcset($item): string
    {
        $thumb = static::getProductDerivativeUrl($item, 'thumbnail');
        $card = static::getProductDerivativeUrl($item, 'card');
        $large = static::getProductDerivativeUrl($item, 'large');

        if (empty($thumb) && empty($card) && empty($large)) {
            return '';
        }

        $sources = [];
        if (!empty($thumb)) {
            $sources[] = "{$thumb} 200w";
        }
        if (!empty($card)) {
            $sources[] = "{$card} 400w";
        }
        if (!empty($large)) {
            $sources[] = "{$large} 800w";
        }

        return implode(', ', array_unique($sources));
    }

    /**
     * Canonical product primary image resolver with derivative support.
     */
    public static function getProductPrimaryImage($product, string $size = 'card'): string
    {
        if (empty($product)) {
            return static::getImageUrl(null);
        }

        return static::getProductDerivativeUrl($product, $size);
    }

    /**
     * Resolves an array of images into clean canonical URLs with derivative support.
     * Returns only valid, real images without generic placeholders.
     */
    public static function resolveImageList($product, string $size = 'large'): array
    {
        if (empty($product)) {
            return [];
        }

        $list = [];

        // Add primary featured image if set
        $featured = is_object($product) ? ($product->featured_image ?? null) : ($product['featured_image'] ?? null);
        $extracted = static::extractImagePath($featured);
        if (!empty($extracted)) {
            $url = static::getProductDerivativeUrl($extracted, $size);
            if (!empty($url) && !str_contains($url, 'placeholder')) {
                $list[] = $url;
            }
        }

        // Add gallery images
        $images = is_object($product) ? ($product->images ?? null) : ($product['images'] ?? null);
        if (is_array($images)) {
            foreach ($images as $img) {
                $candidate = static::extractImagePath($img);
                if (!empty($candidate)) {
                    $url = static::getProductDerivativeUrl($candidate, $size);
                    if (!empty($url) && !str_contains($url, 'placeholder') && !in_array($url, $list)) {
                        $list[] = $url;
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Strips HTML tags and decodes entities safely.
     */
    public static function stripHtml(?string $html): string
    {
        if (empty($html)) return '';
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Sanitizes rich text descriptions and cleans empty tags.
     */
    public static function formatRichHtml(?string $html): string
    {
        if (empty($html)) return '';

        // If pure plain text without tags, wrap in proper paragraphs
        if (!preg_match('/<[a-z][\s\S]*>/i', $html)) {
            $paragraphs = array_filter(array_map('trim', preg_split('/\n\n+/', $html)));
            if (empty($paragraphs)) return '';
            return implode('', array_map(function ($p) {
                return '<p>' . nl2br(e($p)) . '</p>';
            }, $paragraphs));
        }

        // 1. Remove dangerous executable tags
        $cleaned = preg_replace('/<(script|iframe|object|embed|applet|meta|link|base|style)\b[^<]*(?:(?!<\/\1>)<[^<]*)*<\/\1>/i', '', $html);
        $cleaned = preg_replace('/<(script|iframe|object|embed|applet|meta|link|base|style)\b[^>]*\/?>/i', '', $cleaned);

        // 2. Strip inline event handlers (onload=, onclick=, etc) and pseudo-protocol URIs
        $cleaned = preg_replace('/\s+on[a-z]+\s*=\s*(?:' . "'[^']*'|" . '"[^"]*"|[^\s>]+)/i', '', $cleaned);
        $cleaned = preg_replace('/(href|src)\s*=\s*([\'"]?)\s*(javascript|vbscript|data):/i', '$1=$2about:blank#', $cleaned);

        // 3. Clean empty paragraphs & meaningless whitespace tags
        $cleaned = preg_replace('/<p>\s*(<br\s*\/?>|&nbsp;|\s)*<\/p>/i', '', $cleaned);
        $cleaned = preg_replace('/<div>\s*(<br\s*\/?>|&nbsp;|\s)*<\/div>/i', '', $cleaned);
        $cleaned = preg_replace('/<span>\s*(<br\s*\/?>|&nbsp;|\s)*<\/span>/i', '', $cleaned);

        return trim($cleaned);
    }

    /**
     * Formats price strictly in Nepali numbering (e.g. 100000 -> "Rs. 1,00,000").
     */
    public static function formatPrice($amount, string $currency = 'Rs.'): string
    {
        $prefix = (trim($currency) === 'NPR' || trim($currency) === 'Rs.' || trim($currency) === 'Rs') ? 'Rs. ' : (rtrim($currency) . ' ');
        return \App\Helpers\NepaliNumberHelper::formatCurrency($amount, $prefix);
    }
}
