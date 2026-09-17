<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get paginated products with faceted filtering, search, and sorting.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['categories:id,name,slug', 'collections:id,name,slug', 'variantsList'])
            ->where('is_published', true)
            ->where('is_active', true);

        // Catalog out-of-stock filtering behavior
        $outOfStockBehavior = app(\App\Services\Settings\SettingsService::class)->getString('commerce', 'out_of_stock_behavior', 'show_unavailable');
        if ($outOfStockBehavior === 'hide') {
            $query->where(function ($q) {
                $q->where('quantity', '>', 0)
                    ->orWhere('allow_preorder', true)
                    ->orWhereHas('variantsList', function ($vq) {
                        $vq->where('stock_quantity', '>', 0);
                    });
            });
        }

        // Filter by explicit IDs (e.g. for wishlist queries)
        if ($request->filled('ids')) {
            $ids = array_filter(array_map('trim', explode(',', $request->input('ids'))));
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        // Filter by Category slug
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $cat = $request->input('category');
            $query->whereHas('categories', function ($q) use ($cat) {
                $q->where('slug', $cat)->orWhere('name', 'like', "%{$cat}%");
            });
        }

        // Filter by Collection slug
        if ($request->filled('collection') && $request->input('collection') !== 'all') {
            $col = $request->input('collection');
            $query->whereHas('collections', function ($q) use ($col) {
                $q->where('slug', $col)->orWhere('name', 'like', "%{$col}%");
            });
        }

        // Filter by Featured
        if ($request->boolean('featured') || $request->input('is_featured') === 'true') {
            $query->where('is_featured', true);
        }

        // Filter by New Arrivals
        if ($request->boolean('new_arrivals') || $request->input('is_featured') === 'true') {
            $newArrivalDays = app(\App\Services\Settings\SettingsService::class)->getInteger('commerce', 'new_arrival_threshold_days', 60);

            // Prioritize explicitly marked is_new_arrival or pieces created within configured threshold
            $hasExplicitOrRecent = (clone $query)->where(function ($q) use ($newArrivalDays) {
                $q->where('is_new_arrival', true)
                    ->orWhere('created_at', '>=', now()->subDays($newArrivalDays));
            })->exists();

            if ($hasExplicitOrRecent) {
                $query->where(function ($q) use ($newArrivalDays) {
                    $q->where('is_new_arrival', true)
                        ->orWhere('created_at', '>=', now()->subDays($newArrivalDays));
                });
            }
            // Always sort newest first for new arrivals by default
            if (!$request->filled('sort') || $request->input('sort') === 'newest') {
                $query->latest();
            }
        }

        // Search in Name, SKU, Short Description, Description, Material, Fabric, Categories, Collections
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('material', 'like', "%{$term}%")
                    ->orWhere('fabric', 'like', "%{$term}%")
                    ->orWhereHas('categories', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
                    })
                    ->orWhereHas('collections', function ($colq) use ($term) {
                        $colq->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%");
                    });
            });
        }

        // Filter by Size via variants
        if ($request->filled('size')) {
            $size = $request->input('size');
            $query->whereHas('variantsList', function ($q) use ($size) {
                $q->where('size', $size);
            });
        }

        // Filter by Color via variants
        if ($request->filled('color')) {
            $color = $request->input('color');
            $query->whereHas('variantsList', function ($q) use ($color) {
                $q->where('color', 'like', "%{$color}%");
            });
        }

        $currency = 'NPR';
        $priceColumn = 'price';

        // Price range
        if ($request->filled('min_price')) {
            $query->where($priceColumn, '>=', (float)$request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where($priceColumn, '<=', (float)$request->input('max_price'));
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw("COALESCE(price, price_npr) ASC");
                break;
            case 'price_desc':
                $query->orderByRaw("COALESCE(price, price_npr) DESC");
                break;
            case 'featured':
                $query->orderBy('is_featured', 'desc')->latest();
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $perPage = min(50, max(1, (int)$request->input('per_page', 12)));
        $paginator = $query->paginate($perPage);

        $commerceService = app(\App\Services\CommerceService::class);
        $newArrivalDays = app(\App\Services\Settings\SettingsService::class)->getInteger('commerce', 'new_arrival_threshold_days', 60);

        $paginator->getCollection()->transform(function (Product $product) use ($currency, $request, $commerceService, $newArrivalDays) {
            $product->omnibus_30_day_lowest = $product->getLowestPriceInLast30Days($currency);
            if ($request->boolean('new_arrivals') || ($product->created_at && $product->created_at->gte(now()->subDays($newArrivalDays)))) {
                $product->is_new_arrival = true;
            }
            $product->stock_visibility = $commerceService->getStockVisibility($product);
            return $product;
        });

        return response()->json($paginator);
    }

    /**
     * Get single product by ID or Slug.
     */
    public function show(string $identifier, Request $request)
    {
        $currency = 'NPR';

        $query = Product::with(['categories', 'collections', 'variantsList'])
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier);
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int)$identifier);
                }
            });

        if (!$request->user() && !$request->has('preview')) {
            $query->where('is_published', true)->where('is_active', true);
        }

        $product = $query->firstOrFail();

        $commerceService = app(\App\Services\CommerceService::class);
        $product->omnibus_30_day_lowest = $product->getLowestPriceInLast30Days($currency);
        $product->stock_visibility = $commerceService->getStockVisibility($product);

        if ($product->relationLoaded('variantsList')) {
            $product->variantsList->transform(function ($v) use ($product, $commerceService) {
                $v->stock_visibility = $commerceService->getStockVisibility($product, $v);
                return $v;
            });
        }

        return response()->json([
            'data' => $product
        ]);
    }
}
