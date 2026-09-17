<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class StorageAssetController extends Controller
{
    /**
     * Stream public storage assets (product images, thumbnails, uploads) with high-performance caching.
     * Acts as an authoritative, self-healing media gateway across all deployment environments (cPanel, Apache, Nginx, Shared Hosting).
     */
    public function show(Request $request, string $path): Response
    {
        // 1. Sanitize subpath against directory traversal and decode URL encoding
        $rawDecoded = urldecode($path);
        $cleanPath = str_replace(['../', '..\\', "\0"], '', $rawDecoded);
        
        // Strip redundant prefixes if accidentally included in request URI
        $cleanPath = preg_replace('#^(\/?public\/|\/?storage\/|\/?app\/public\/)+#i', '', $cleanPath);
        $cleanPath = ltrim($cleanPath, '/\\');

        if (empty($cleanPath)) {
            abort(404, 'Storage asset path is empty.');
        }

        // 2. Multi-tier Path Resolution
        $targetFile = $this->resolveFile($cleanPath);

        if (!$targetFile) {
            // Check if client requested an image asset
            $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'avif', 'ico'], true)) {
                // Return a lightweight, elegant branded placeholder SVG so frontend layout never breaks
                return $this->renderFallbackSvg($cleanPath);
            }

            abort(404, 'Image or storage asset not found.');
        }

        // 3. Determine Content-Type
        $extension = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'webp' => 'image/webp',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'avif' => 'image/avif',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
        ];

        $mimeType = $mimeMap[$extension] ?? (File::mimeType($targetFile) ?: 'application/octet-stream');

        // 4. HTTP 304 Not Modified & Caching Headers
        $lastModified = (int) filemtime($targetFile);
        $fileSize = (int) filesize($targetFile);
        $etag = sprintf('"%x-%x"', $lastModified, $fileSize);

        $ifNoneMatch = $request->header('If-None-Match');
        $ifModifiedSince = $request->header('If-Modified-Since');

        if ($ifNoneMatch === $etag || ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified)) {
            return response('', 304, [
                'ETag' => $etag,
                'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return response()->file($targetFile, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Resolve target file using intelligent candidate strategies.
     */
    protected function resolveFile(string $path): ?string
    {
        $candidatePaths = $this->buildCandidatePaths($path);

        foreach ($candidatePaths as $candidate) {
            if (File::exists($candidate) && !is_dir($candidate)) {
                return $candidate;
            }
        }

        // Try case-insensitive lookup in matching directory
        foreach ($candidatePaths as $candidate) {
            $resolved = $this->resolveCaseInsensitive($candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Build search candidates for a requested storage path.
     */
    protected function buildCandidatePaths(string $cleanPath): array
    {
        $candidates = [];

        // 1. Direct storage/app/public/
        $candidates[] = storage_path('app/public/' . $cleanPath);

        // 2. Singular / Plural directory interchange
        $dirSwaps = [
            'products/'    => 'product/',
            'product/'     => 'products/',
            'categories/'  => 'category/',
            'category/'    => 'categories/',
            'collections/' => 'collection/',
            'collection/'  => 'collections/',
            'banners/'     => 'banner/',
            'banner/'      => 'banners/',
            'brands/'      => 'brand/',
            'brand/'       => 'brands/',
            'shops/'       => 'shop/',
            'shop/'        => 'shops/',
            'profiles/'    => 'profile/',
            'profile/'     => 'profiles/',
        ];

        foreach ($dirSwaps as $from => $to) {
            if (str_starts_with($cleanPath, $from)) {
                $swapped = $to . substr($cleanPath, strlen($from));
                $candidates[] = storage_path('app/public/' . $swapped);
            }
        }

        // 3. Alternate common image extensions (.webp <-> .jpg <-> .png)
        $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'avif'], true)) {
            $baseWithoutExt = substr($cleanPath, 0, -strlen($ext) - 1);
            $altExts = array_diff(['webp', 'jpg', 'jpeg', 'png', 'avif'], [$ext]);

            foreach ($altExts as $altExt) {
                $candidates[] = storage_path('app/public/' . $baseWithoutExt . '.' . $altExt);
                foreach ($dirSwaps as $from => $to) {
                    if (str_starts_with($baseWithoutExt, $from)) {
                        $swappedBase = $to . substr($baseWithoutExt, strlen($from));
                        $candidates[] = storage_path('app/public/' . $swappedBase . '.' . $altExt);
                    }
                }
            }
        }

        // 4. storage/app/
        $candidates[] = storage_path('app/' . $cleanPath);

        // 5. public/ root and public/images/
        $candidates[] = public_path($cleanPath);
        $candidates[] = public_path('images/' . $cleanPath);
        $candidates[] = public_path('storage/' . $cleanPath);

        return array_unique($candidates);
    }

    /**
     * Case-insensitive file existence resolver for Linux filesystems.
     */
    protected function resolveCaseInsensitive(string $targetFile): ?string
    {
        $dir = dirname($targetFile);
        $filename = basename($targetFile);

        if (!is_dir($dir)) {
            return null;
        }

        $lowerFilename = strtolower($filename);
        $entries = @scandir($dir);
        if (!$entries) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) === $lowerFilename) {
                $found = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Render a clean, branded, lightweight fallback SVG when an image is permanently missing.
     */
    protected function renderFallbackSvg(string $path): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600" fill="none">'
            . '<rect width="600" height="600" fill="#F8FAFC"/>'
            . '<rect x="180" y="180" width="240" height="240" rx="32" fill="#E2E8F0"/>'
            . '<path d="M250 330L280 290L310 330L340 280L370 330H250Z" fill="#94A3B8"/>'
            . '<circle cx="280" cy="250" r="16" fill="#94A3B8"/>'
            . '<text x="300" y="460" font-family="system-ui, -apple-system, sans-serif" font-size="20" font-weight="700" fill="#64748B" text-anchor="middle" letter-spacing="2">LAIJAU</text>'
            . '</svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
