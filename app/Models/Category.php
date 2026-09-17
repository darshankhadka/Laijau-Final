<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'image',
        'description',
        'seo_title',
        'seo_description',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = \Illuminate\Support\Str::slug($category->name);
            }
        });

        static::saving(function (Category $category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = \Illuminate\Support\Str::slug($category->name);
            }

            $raw = $category->attributes['image'] ?? null;
            if (!empty($raw) && $category->isDirty('image')) {
                $optimized = \App\Services\ImageOptimizerService::optimizeStoragePath($raw);
                if ($optimized) {
                    $category->attributes['image'] = $optimized;
                }
            }
        });

        static::saved(function (Category $category) {
            \Illuminate\Support\Facades\Cache::forget('public_categories_list');
            if ($category->slug) {
                \Illuminate\Support\Facades\Cache::forget('category_slug_' . $category->slug);
            }
            if ($category->isDirty('slug') && $category->getOriginal('slug')) {
                \Illuminate\Support\Facades\Cache::forget('category_slug_' . $category->getOriginal('slug'));
            }
        });

        static::deleted(function (Category $category) {
            \Illuminate\Support\Facades\Cache::forget('public_categories_list');
            if ($category->slug) {
                \Illuminate\Support\Facades\Cache::forget('category_slug_' . $category->slug);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // --- Hierarchy Helpers ---

    /**
     * Recursively collect all descendant category IDs.
     */
    public function getAllChildrenIds(): array
    {
        $ids = [];
        $children = $this->children()->get(['id']);
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }
        return array_unique($ids);
    }

    /**
     * Get Query builder for products in this category and all its descendants.
     */
    public function getAllProductsQuery()
    {
        $categoryIds = array_merge([$this->id], $this->getAllChildrenIds());

        return Product::whereHas('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        });
    }

    /**
     * Formatted taxonomy breadcrumb path (e.g. 'Women > Footwear > Leather Boots').
     */
    public function getHierarchyPathAttribute(): string
    {
        $path = [$this->name];
        $curr = $this;

        while ($curr->parent_id) {
            $parent = $curr->relationLoaded('parent') ? $curr->parent : Category::withoutGlobalScopes()->find($curr->parent_id);
            if (!$parent) {
                break;
            }
            array_unshift($path, $parent->name);
            $curr = $parent;
        }

        return implode(' > ', $path);
    }

    public function getUrlAttribute(): string
    {
        return route('storefront.category', $this->slug);
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
