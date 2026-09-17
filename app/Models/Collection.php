<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_published',
        'is_featured',
        'sort_order',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Collection $collection) {
            $raw = $collection->attributes['image'] ?? null;
            if (!empty($raw) && $collection->isDirty('image')) {
                $optimized = \App\Services\ImageOptimizerService::optimizeStoragePath($raw);
                if ($optimized) {
                    $collection->attributes['image'] = $optimized;
                }
            }
        });

        static::saved(function (Collection $collection) {
            \Illuminate\Support\Facades\Cache::forget('public_collections_list');
            if ($collection->slug) {
                \Illuminate\Support\Facades\Cache::forget('collection_slug_' . $collection->slug);
            }
        });

        static::deleted(function (Collection $collection) {
            \Illuminate\Support\Facades\Cache::forget('public_collections_list');
            if ($collection->slug) {
                \Illuminate\Support\Facades\Cache::forget('collection_slug_' . $collection->slug);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function getImageAttribute(?string $value): ?string
    {
        if (!$value) return null;
        if (str_contains($value, '/storage/')) {
            return ltrim(substr($value, strpos($value, '/storage/') + strlen('/storage/')), '/\\');
        }
        return $value;
    }
}
