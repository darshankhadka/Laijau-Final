<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchSuggestionController extends Controller
{
    /**
     * Return live autocomplete suggestions for the storefront search bar.
     */
    public function index(Request $request): JsonResponse
    {
        $query = trim((string)$request->query('q', ''));

        if (mb_strlen($query) < 2) {
            // Return default quick links and top categories
            $topCategories = Category::where('is_active', true)
                ->withCount(['products' => function ($q) {
                    $q->where('is_active', true);
                }])
                ->orderByDesc('products_count')
                ->limit(6)
                ->get(['id', 'name', 'slug', 'image']);

            return response()->json([
                'query' => '',
                'popular_searches' => [
                    'Sneakers',
                    'Chelsea Boots',
                    'Running Shoes',
                    'Timberland',
                    'Party Shoes',
                    'Hoodies',
                    'Casual Shoes',
                    'Loafers',
                ],
                'categories' => $topCategories->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'image' => \App\Helpers\StorefrontHelper::getProductDerivativeUrl($c->image, 'thumbnail'),
                    'count' => $c->products_count,
                ]),
                'products' => [],
                'total_products_count' => 0,
            ]);
        }

        // Search products (photo-backed storefront products only)
        $productsQuery = Product::storefrontReady()->searchRetail($query);
        $totalProductMatches = (clone $productsQuery)->count();

        // If 0 matches on strict search with multiple tokens, attempt relaxed OR search
        $tokens = array_values(array_filter(preg_split('/[\s\-_,\/]+/', $query), fn($t) => mb_strlen(trim($t)) > 0));
        if ($totalProductMatches === 0 && count($tokens) > 1) {
            $productsQuery = Product::storefrontReady()->searchRetailOr($query);
            $totalProductMatches = (clone $productsQuery)->count();
        }

        $products = $productsQuery
            ->select(['id', 'name', 'slug', 'sku', 'price', 'compare_at_price', 'featured_image'])
            ->limit(6)
            ->get();

        // Search categories
        $categories = Category::where('is_active', true)
            ->where(function ($cq) use ($query, $tokens) {
                $cq->where('name', 'like', "%{$query}%");
                foreach ($tokens as $t) {
                    $cq->orWhere('name', 'like', "%{$t}%");
                }
            })
            ->withCount(['products' => function ($q) {
                $q->storefrontReady();
            }])
            ->limit(4)
            ->get(['id', 'name', 'slug', 'image']);

        // Suggestions / Query hints
        $suggestedQueries = [];
        foreach ($products as $p) {
            // Extract useful 2-3 word phrases matching query
            $words = explode(' ', $p->name);
            if (count($words) >= 2) {
                $phrase = implode(' ', array_slice($words, 0, 3));
                if (!in_array($phrase, $suggestedQueries, true) && count($suggestedQueries) < 4) {
                    $suggestedQueries[] = $phrase;
                }
            }
        }

        return response()->json([
            'query' => $query,
            'popular_searches' => $suggestedQueries,
            'categories' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image' => \App\Helpers\StorefrontHelper::getProductDerivativeUrl($c->image, 'thumbnail'),
                'count' => $c->products_count,
            ]),
            'products' => $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'sku' => $p->sku,
                'price' => (float)$p->price,
                'formatted_price' => \App\Helpers\NepaliNumberHelper::formatCurrency($p->price),
                'compare_at_price' => $p->compare_at_price ? (float)$p->compare_at_price : null,
                'formatted_compare_at_price' => $p->compare_at_price ? \App\Helpers\NepaliNumberHelper::formatCurrency($p->compare_at_price) : null,
                'image' => \App\Helpers\StorefrontHelper::getProductDerivativeUrl($p->featured_image, 'thumbnail'),
                'url' => route('storefront.product', $p->slug),
            ]),
            'total_products_count' => $totalProductMatches,
        ]);
    }
}
