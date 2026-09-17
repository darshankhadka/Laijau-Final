<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Cache::rememberForever('public_categories_list', function () {
            return Category::where('is_active', true)
                ->withCount(['products' => function ($q) {
                    $q->where('is_published', true)->where('is_active', true);
                }])
                ->orderBy('sort_order', 'asc')
                ->get()
                ->toArray();
        });

        return response()->json([
            'data' => $categories
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }

    public function show(string $slug)
    {
        $category = Cache::remember('category_slug_' . $slug, 3600, function () use ($slug) {
            return Category::where('slug', $slug)->firstOrFail()->toArray();
        });

        return response()->json([
            'data' => $category
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
