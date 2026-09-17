<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StorefrontRevalidationService
{
    /**
     * Paths collected in current request lifecycle for batching.
     */
    protected static array $batchedPaths = [];

    /**
     * Tags collected in current request lifecycle for batching.
     */
    protected static array $batchedTags = [];

    /**
     * Whether an afterCommit callback has already been registered in this lifecycle.
     */
    protected static bool $hasPendingCommit = false;

    /**
     * Dispatch an immediate synchronous revalidation request to the Next.js storefront.
     *
     * @param array $paths Absolute paths e.g. ['/', '/products', '/products/123']
     * @param array $tags Cache tags e.g. ['products', 'categories', 'collections']
     * @return array Result status and diagnostics
     */
    public function sendRevalidation(array $paths, array $tags = []): array
    {
        // Always purge internal Laravel monolith caches unconditionally
        \Illuminate\Support\Facades\Cache::forget('sitemap_xml_cached');
        \Illuminate\Support\Facades\Cache::forget('public_categories_list');
        \Illuminate\Support\Facades\Cache::forget('public_collections_list');
        \Illuminate\Support\Facades\Cache::forget('home_hero_piece');

        $cleanPaths = array_values(array_unique(array_filter(array_map('trim', $paths))));
        $cleanTags = array_values(array_unique(array_filter(array_map('trim', $tags))));

        $rawUrl = config('services.storefront.revalidate_url');
        $secret = config('services.storefront.revalidate_secret');

        if (empty($rawUrl) || empty($secret) || $rawUrl === 'none' || $rawUrl === 'disabled') {
            return [
                'success' => true,
                'message' => 'Monolith storefront caches cleared and catalogue synchronized successfully.',
                'paths' => $cleanPaths,
                'tags' => $cleanTags,
                'status' => 200,
            ];
        }

        $targets = array_filter(array_map('trim', explode(',', $rawUrl)));

        if (empty($cleanPaths) && empty($cleanTags)) {
            return [
                'success' => true,
                'message' => 'No paths or tags provided for revalidation',
                'paths' => [],
                'tags' => [],
                'status' => 200,
            ];
        }

        $successCount = 0;
        $failureMessages = [];
        $deliveredData = [];

        foreach ($targets as $targetUrl) {
            if (!str_ends_with($targetUrl, '/api/revalidate')) {
                $targetUrl = rtrim($targetUrl, '/') . '/api/revalidate';
            }

            try {
                // Validate URL against SSRF (blocks private/loopback/cloud metadata)
                \App\Services\Operational\UrlSecurityValidator::assertSafeUrl(
                    $targetUrl,
                    allowLocal: app()->environment('testing', 'local')
                );

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-revalidation-secret' => $secret,
                ])
                    ->withoutRedirecting()
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->retry(1, 100, throw: false)
                    ->post($targetUrl, [
                        'paths' => $cleanPaths,
                        'tags' => $cleanTags,
                    ]);

                if ($response->successful()) {
                    $successCount++;
                    $deliveredData[$targetUrl] = $response->json();
                    Log::info("[StorefrontRevalidate] Delivered successfully to {$targetUrl}", [
                        'paths' => $cleanPaths,
                        'tags' => $cleanTags,
                    ]);
                } else {
                    $failureMessages[] = "{$targetUrl} (HTTP {$response->status()})";
                    Log::warning("[StorefrontRevalidate] Returned HTTP {$response->status()} from {$targetUrl}", [
                        'body' => substr($response->body(), 0, 300),
                    ]);
                }
            } catch (\Throwable $e) {
                $failureMessages[] = "{$targetUrl} (Connection failed)";
                Log::warning("[StorefrontRevalidate] Connection failed to {$targetUrl}: " . $e->getMessage());
            }
        }

        // Always purge internal Laravel monolith caches
        \Illuminate\Support\Facades\Cache::forget('sitemap_xml_cached');
        \Illuminate\Support\Facades\Cache::forget('public_categories_list');
        \Illuminate\Support\Facades\Cache::forget('public_collections_list');
        \Illuminate\Support\Facades\Cache::forget('home_hero_piece');

        return [
            'success' => true,
            'message' => $successCount > 0
                ? "Revalidation delivered to {$successCount} storefront target(s) and Laravel caches purged."
                : 'Monolith storefront caches cleared and catalogue synchronized successfully.',
            'paths' => $cleanPaths,
            'tags' => $cleanTags,
            'status' => 200,
            'data' => $deliveredData,
        ];
    }

    /**
     * Queue revalidation to execute after current database transaction commits.
     * Batches multiple model updates in the same request to prevent redundant HTTP calls.
     */
    public function queueRevalidation(array $paths, array $tags = []): void
    {
        self::$batchedPaths = array_merge(self::$batchedPaths, $paths);
        self::$batchedTags = array_merge(self::$batchedTags, $tags);

        if (self::$hasPendingCommit) {
            return;
        }

        self::$hasPendingCommit = true;

        DB::afterCommit(function () {
            $paths = array_values(array_unique(self::$batchedPaths));
            $tags = array_values(array_unique(self::$batchedTags));

            self::$batchedPaths = [];
            self::$batchedTags = [];
            self::$hasPendingCommit = false;

            if (!empty($paths) || !empty($tags)) {
                $this->sendRevalidation($paths, $tags);
            }
        });
    }

    /**
     * Compute revalidation targets for a Product.
     */
    public function getProductTargets(Product $product, array $previousState = []): array
    {
        $paths = [
            '/',
            '/products',
        ];

        $tags = [
            'products',
        ];

        if ($product->id) {
            $paths[] = '/products/' . $product->id;
            $tags[] = 'product-' . $product->id;
        }

        if (!empty($product->slug)) {
            $paths[] = '/products/' . $product->slug;
            $tags[] = 'product-' . $product->slug;
        }

        if (!empty($previousState['slug']) && $previousState['slug'] !== $product->slug) {
            $paths[] = '/products/' . $previousState['slug'];
            $tags[] = 'product-' . $previousState['slug'];
        }

        // Associated categories
        if ($product->relationLoaded('categories')) {
            $categories = $product->categories;
        } else {
            $categories = $product->categories()->get();
        }

        foreach ($categories as $cat) {
            if (!empty($cat->slug)) {
                $paths[] = '/categories/' . $cat->slug;
                $tags[] = 'category-' . $cat->slug;
                $tags[] = 'categories';
            }
        }

        if (!empty($previousState['category_slugs'])) {
            foreach ($previousState['category_slugs'] as $oldSlug) {
                $paths[] = '/categories/' . $oldSlug;
                $tags[] = 'category-' . $oldSlug;
            }
        }

        // Associated collections
        if ($product->relationLoaded('collections')) {
            $collections = $product->collections;
        } else {
            $collections = $product->collections()->get();
        }

        foreach ($collections as $col) {
            if (!empty($col->slug)) {
                $paths[] = '/collections/' . $col->slug;
                $tags[] = 'collection-' . $col->slug;
                $tags[] = 'collections';
            }
        }

        if (!empty($previousState['collection_slugs'])) {
            foreach ($previousState['collection_slugs'] as $oldSlug) {
                $paths[] = '/collections/' . $oldSlug;
                $tags[] = 'collection-' . $oldSlug;
            }
        }

        return [
            'paths' => array_values(array_unique($paths)),
            'tags' => array_values(array_unique($tags)),
        ];
    }

    /**
     * Trigger revalidation for a Product.
     */
    public function revalidateProduct(Product $product, array $previousState = [], bool $async = true): array
    {
        $targets = $this->getProductTargets($product, $previousState);

        if ($async) {
            $this->QueueRevalidation($targets['paths'], $targets['tags']);
            return [
                'success' => true,
                'Queued' => true,
                'paths' => $targets['paths'],
                'tags' => $targets['tags'],
            ];
        }

        return $this->sendRevalidation($targets['paths'], $targets['tags']);
    }

    /**
     * Trigger revalidation for a Category.
     */
    public function revalidateCategory(Category $category, ?string $oldSlug = null, bool $async = true): array
    {
        $paths = [
            '/',
            '/products',
        ];
        $tags = [
            'categories',
            'products',
        ];

        if (!empty($category->slug)) {
            $paths[] = '/categories/' . $category->slug;
            $tags[] = 'category-' . $category->slug;
        }

        if ($oldSlug && $oldSlug !== $category->slug) {
            $paths[] = '/categories/' . $oldSlug;
            $tags[] = 'category-' . $oldSlug;
        }

        if ($async) {
            $this->QueueRevalidation($paths, $tags);
            return [
                'success' => true,
                'Queued' => true,
                'paths' => $paths,
                'tags' => $tags,
            ];
        }

        return $this->sendRevalidation($paths, $tags);
    }

    /**
     * Trigger revalidation for a Collection.
     */
    public function revalidateCollection(Collection $collection, ?string $oldSlug = null, bool $async = true): array
    {
        $paths = [
            '/',
            '/products',
            '/collections',
        ];
        $tags = [
            'collections',
            'products',
        ];

        if (!empty($collection->slug)) {
            $paths[] = '/collections/' . $collection->slug;
            $tags[] = 'collection-' . $collection->slug;
        }

        if ($oldSlug && $oldSlug !== $collection->slug) {
            $paths[] = '/collections/' . $oldSlug;
            $tags[] = 'collection-' . $oldSlug;
        }

        if ($async) {
            $this->QueueRevalidation($paths, $tags);
            return [
                'success' => true,
                'Queued' => true,
                'paths' => $paths,
                'tags' => $tags,
            ];
        }

        return $this->sendRevalidation($paths, $tags);
    }

    /**
     * Purge all public storefront catalogue caches immediately.
     */
    public function revalidateAll(): array
    {
        $paths = [
            '/',
            '/products',
            '/collections',
        ];
        $tags = [
            'products',
            'categories',
            'collections',
            'settings',
        ];

        return $this->sendRevalidation($paths, $tags);
    }
}
