<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Cache::rememberForever('public_collections_list', function () {
            return Collection::where('is_published', true)
                ->withCount(['products' => function ($q) {
                    $q->where('is_published', true)->where('is_active', true);
                }])
                ->orderBy('sort_order', 'asc')
                ->get()
                ->toArray();
        });

        return response()->json([
            'data' => $collections
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }

    public function show(string $slug)
    {
        $collection = Cache::remember('collection_slug_' . $slug, 3600, function () use ($slug) {
            return Collection::where('slug', $slug)
                ->where('is_published', true)
                ->with(['products' => function ($q) {
                    $q->where('is_published', true)->where('is_active', true)->with('categories');
                }])
                ->firstOrFail()
                ->toArray();
        });

        return response()->json([
            'data' => $collection
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }
}
