<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'short_description',
        'prices',
        'lowest_price_30_days',
        'images',
        'seo_title',
        'seo_description',
        'og_image',
        'sku',
        'price',
        'price_npr',
        'compare_at_price',
        'compare_at_price_npr',
        'cost_price',
        'cost_price_npr',
        'wholesale_price',
        'tax_class',
        'quantity',
        'track_quantity',
        'low_stock_threshold',
        'weight',
        'dimensions',
        'material',
        'fabric',
        'care_instructions',
        'country_of_origin',
        'is_active',
        'is_published',
        'is_featured',
        'is_new_arrival',
        'featured_image',
        'availability_status',
        'allow_preorder',
        'preorder_expected_dispatch',
        'preorder_limit',
        'preorder_count',
        'supplier_name',
        'supplier_sku',
        'internal_reference',
        'brand',
        'model',
        'barcode',
    ];

    protected $casts = [
        'prices' => 'array',
        'lowest_price_30_days' => 'decimal:2',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'images' => 'array',
        'track_quantity' => 'boolean',
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'allow_preorder' => 'boolean',
        'preorder_limit' => 'integer',
        'preorder_count' => 'integer',
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'is_new_arrival' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            // Guarantee unique, deterministic, collision-safe SKU if not explicitly provided
            if (empty($product->sku)) {
                $product->sku = app(\App\Services\ProductSkuService::class)->generateForProduct($product);
            }
            if (empty($product->barcode)) {
                $product->barcode = app(\App\Services\ProductSkuService::class)->generateBarcodeForProduct($product);
            }
        });

        static::updating(function (Product $product) {
            // Guard: never regenerate or erase an existing SKU on product updates
            if ($product->isDirty('sku') && empty($product->sku) && !empty($product->getOriginal('sku'))) {
                $product->sku = $product->getOriginal('sku');
            }
        });

        static::saved(function (Product $product) {
            // Synchronize price and cost changes to related variants
            $priceChanges = [];
            if ($product->wasChanged('price') && $product->price !== null) {
                $priceChanges['price'] = $product->price;
            }
            if ($product->wasChanged('cost_price') && $product->cost_price !== null) {
                $priceChanges['cost_price'] = $product->cost_price;
            }
            if ($product->wasChanged('wholesale_price') && $product->wholesale_price !== null) {
                $priceChanges['wholesale_price'] = $product->wholesale_price;
            }
            if (!empty($priceChanges) && $product->variants()->exists()) {
                $product->variants()->update($priceChanges);
            }

            // Synchronize default warehouse StockLevels when quantity is mutated directly
            if ($product->wasChanged('quantity') && !$product->variants()->exists()) {
                try {
                    $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                    $defaultWh = $inventoryService->getDefaultWarehouse();
                    if ($defaultWh) {
                        \App\Models\Inventory\StockLevel::updateOrCreate(
                            [
                                'warehouse_id' => $defaultWh->id,
                                'product_id' => $product->id,
                                'variant_id' => null,
                            ],
                            [
                                'quantity_on_hand' => (int)$product->quantity,
                                'unit_cost_npr' => (float)($product->cost_price ?: 0),
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Suppress during early migration setup if table not yet migrated
                }
            }
        });

        static::saving(function (Product $product) {
            // Guarantee unique deterministic slug
            if (empty($product->slug) && !empty($product->name)) {
                $product->slug = \Illuminate\Support\Str::slug($product->name);
            }
            if (!empty($product->slug) && ($product->isDirty('slug') || !$product->exists)) {
                $baseSlug = \Illuminate\Support\Str::slug($product->slug);
                $slug = $baseSlug;
                $count = 1;
                while (static::where('slug', $slug)->where('id', '!=', $product->id ?? 0)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }
                $product->slug = $slug;
            }

            // Guarantee type and prices for legacy non-null database compatibility
            if (empty($product->type)) {
                $product->type = 'apparel';
            }

            // Sync legacy columns for DB schema compatibility without foreign currency formulas
            if ($product->price && empty($product->price_npr)) {
                $product->price_npr = $product->price;
            } elseif ($product->price_npr && empty($product->price)) {
                $product->price = $product->price_npr;
            }

            if ($product->cost_price && empty($product->cost_price_npr)) {
                $product->cost_price_npr = $product->cost_price;
            } elseif ($product->cost_price_npr && empty($product->cost_price)) {
                $product->cost_price = $product->cost_price_npr;
            }

            if ($product->compare_at_price && empty($product->compare_at_price_npr)) {
                $product->compare_at_price_npr = $product->compare_at_price;
            }

            if (empty($product->price_npr) && $product->price) {
                $product->price_npr = $product->price;
            }
            if (empty($product->cost_price_npr) && $product->cost_price) {
                $product->cost_price_npr = $product->cost_price;
            }
            if (empty($product->compare_at_price_npr) && $product->compare_at_price) {
                $product->compare_at_price_npr = $product->compare_at_price;
            }

            // Keep JSON prices updated with NPR sole standard
            $product->prices = [
                'NPR' => (float) ($product->price ?: $product->price_npr),
            ];

            // Clean empty HTML tags or whitespace from descriptions
            if (array_key_exists('short_description', $product->attributes)) {
                $rawShort = $product->attributes['short_description'];
                if (is_string($rawShort)) {
                    $plainShort = trim(strip_tags($rawShort));
                    $product->attributes['short_description'] = empty($plainShort) ? null : trim($rawShort);
                } elseif (is_array($rawShort)) {
                    $val = reset($rawShort);
                    $plainShort = is_string($val) ? trim(strip_tags($val)) : '';
                    $product->attributes['short_description'] = empty($plainShort) ? null : (is_string($val) ? trim($val) : null);
                } else {
                    $product->attributes['short_description'] = null;
                }
            }
            if (array_key_exists('description', $product->attributes)) {
                $rawDesc = $product->attributes['description'];
                if (is_string($rawDesc)) {
                    $plainDesc = trim(strip_tags($rawDesc));
                    $product->attributes['description'] = empty($plainDesc) ? null : trim($rawDesc);
                } elseif (is_array($rawDesc)) {
                    $val = reset($rawDesc);
                    $plainDesc = is_string($val) ? trim(strip_tags($val)) : '';
                    $product->attributes['description'] = empty($plainDesc) ? null : (is_string($val) ? trim($val) : null);
                } else {
                    $product->attributes['description'] = null;
                }
            }

            // Clean and optimize featured image
            $rawFeatured = $product->attributes['featured_image'] ?? null;
            if (is_array($rawFeatured)) {
                $rawFeatured = reset($rawFeatured);
            }
            if (!empty($rawFeatured) && is_string($rawFeatured)) {
                if (str_contains($rawFeatured, '/storage/')) {
                    $rawFeatured = substr($rawFeatured, strpos($rawFeatured, '/storage/') + strlen('/storage/'));
                    $rawFeatured = ltrim($rawFeatured, '/\\');
                }
                if ($product->isDirty('featured_image')) {
                    $optimized = \App\Services\ImageOptimizerService::optimizeStoragePath($rawFeatured);
                    $product->attributes['featured_image'] = $optimized ?: $rawFeatured;
                } else {
                    $product->attributes['featured_image'] = $rawFeatured;
                }
            }

            $rawImages = $product->attributes['images'] ?? null;
            $cleanImgs = [];
            if (!empty($rawImages)) {
                $imgsArr = is_string($rawImages) ? json_decode($rawImages, true) : ($rawImages ?: []);
                if (is_array($imgsArr)) {
                    foreach ($imgsArr as $img) {
                        if (!empty($img) && is_string($img)) {
                            if (str_contains($img, '/storage/')) {
                                $img = substr($img, strpos($img, '/storage/') + strlen('/storage/'));
                                $img = ltrim($img, '/\\');
                            }
                            if ($product->isDirty('images')) {
                                $optimized = \App\Services\ImageOptimizerService::optimizeStoragePath($img);
                                $cleanImgs[] = $optimized ?: $img;
                            } else {
                                $cleanImgs[] = $img;
                            }
                        }
                    }
                    $product->attributes['images'] = is_string($rawImages) ? json_encode($cleanImgs) : $cleanImgs;
                }
            }

            // Ensure deterministic primary image if featured_image is empty but gallery has photos
            if (empty($product->attributes['featured_image']) && !empty($cleanImgs)) {
                $product->attributes['featured_image'] = $cleanImgs[0];
            }

            // 30-day price history check
            if (($product->isDirty('price') || $product->isDirty('price_npr')) && $product->exists) {
                $origPrice = (float)$product->getOriginal('price') ?: (float)$product->getOriginal('price_npr');
                if ($origPrice > 0) {
                    PriceHistory::create([
                        'product_id' => $product->id,
                        'prices' => [
                            'NPR' => $origPrice,
                        ],
                        'changed_at' => now(),
                    ]);
                }
            }
        });
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function getCategoryAttribute(): ?Category
    {
        return $this->relationLoaded('categories') ? $this->categories->first() : $this->categories()->first();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function variantsList(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function hasVariants(): bool
    {
        return $this->variants()->where('is_active', true)->exists();
    }

    public function getActiveVariants()
    {
        return $this->variants()->where('is_active', true)->get();
    }

    public function getAvailableSizes(): array
    {
        return $this->variants()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->pluck('size')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getAvailableColors(): array
    {
        return $this->variants()
            ->where('is_active', true)
            ->pluck('color')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getLowestPriceInLast30Days(string $currency = 'NPR'): ?float
    {
        if ($this->lowest_price_30_days) {
            return (float)$this->lowest_price_30_days;
        }

        return (float)($this->price ?: ($this->price_npr ?: 0));
    }

    public function getFeaturedImageAttribute(mixed $value): ?string
    {
        if (empty($value)) return null;
        if (is_array($value)) {
            $value = reset($value);
        }
        if (!is_string($value) || empty($value)) return null;
        if (str_contains($value, '/storage/')) {
            return ltrim(substr($value, strpos($value, '/storage/') + strlen('/storage/')), '/\\');
        }
        return $value;
    }

    public function getImagesAttribute(mixed $value): array
    {
        $arr = is_string($value) ? json_decode($value, true) : ($value ?: []);
        if (!is_array($arr)) return [];
        return array_map(function ($img) {
            if (!$img || !is_string($img)) return $img;
            if (str_contains($img, '/storage/')) {
                return ltrim(substr($img, strpos($img, '/storage/') + strlen('/storage/')), '/\\');
            }
            return $img;
        }, $arr);
    }

    public function restockRequests(): HasMany
    {
        return $this->hasMany(RestockRequest::class);
    }

    public function isPreorder(): bool
    {
        return $this->availability_status === 'pre_order' || (bool)$this->allow_preorder;
    }

    public function isPermanentlyUnavailable(): bool
    {
        return $this->availability_status === 'permanently_unavailable';
    }

    public function acceptsRestockRequests(): bool
    {
        if ($this->isPermanentlyUnavailable() || $this->isPreorder()) {
            return false;
        }
        return $this->availability_status === 'restock_requests' || $this->availability_status === 'out_of_stock' || $this->quantity <= 0;
    }

    public function isAvailable(): bool
    {
        if ($this->isPermanentlyUnavailable()) {
            return false;
        }
        if ($this->isPreorder()) {
            return true;
        }
        if ($this->variants()->exists()) {
            return $this->variants()->where('is_active', true)->where('stock_quantity', '>', 0)->exists();
        }
        return $this->quantity > 0 && ($this->availability_status === 'available' || empty($this->availability_status));
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\StockLevel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('is_active', true);
    }

    /**
     * Scope query to only include products eligible for storefront publication.
     * Hard Rule: Product must be published, active, photo-backed with a genuine image,
     * and have a strictly valid, non-zero selling price.
     */
    public function scopeStorefrontReady(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where('is_active', true)
            ->whereNotNull('featured_image')
            ->where('featured_image', '!=', '')
            ->where('featured_image', '!=', 'null')
            ->where('featured_image', 'not like', '%placeholder%')
            ->whereNotNull('price')
            ->where('price', '>', 0);
    }

    /**
     * Check if product has a strictly valid, non-zero selling price.
     */
    public function hasValidPrice(): bool
    {
        return $this->price !== null && (float)$this->price > 0;
    }

    /**
     * Check if product satisfies all criteria for public storefront display.
     */
    public function isStorefrontEligible(): bool
    {
        return $this->is_published
            && $this->is_active
            && $this->hasValidPrice()
            && $this->hasValidPhoto();
    }

    /**
     * Check if product has a valid, existing photo.
     */
    public function hasValidPhoto(): bool
    {
        if (empty($this->featured_image) || str_contains(strtolower($this->featured_image), 'placeholder')) {
            return false;
        }

        $path = $this->featured_image;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return true;
        }

        $clean = ltrim(preg_replace('#^(\/?storage\/)+#', '', $path), '/');
        if (file_exists(public_path('storage/' . $clean))
            || file_exists(public_path($clean))
            || file_exists(storage_path('app/public/' . $clean))
            || file_exists(storage_path('app/' . $clean))) {
            return true;
        }

        // Check alternate directory interchange (product <-> products)
        $altClean = null;
        if (str_starts_with($clean, 'products/')) {
            $altClean = 'product/' . substr($clean, 9);
        } elseif (str_starts_with($clean, 'product/')) {
            $altClean = 'products/' . substr($clean, 8);
        }

        if ($altClean && (file_exists(storage_path('app/public/' . $altClean)) || file_exists(public_path('storage/' . $altClean)))) {
            return true;
        }

        return false;
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeSearchRetail(Builder $query, string $term, string $mode = 'and'): Builder
    {
        $term = trim($term);
        if (empty($term)) {
            return $query;
        }

        $tokens = array_values(array_filter(
            preg_split('/[\s\-_,\/]+/', $term),
            fn($t) => mb_strlen(trim($t)) > 0
        ));

        if (empty($tokens)) {
            return $query;
        }

        return $query->where(function ($q) use ($tokens, $term, $mode) {
            // Full literal phrase match across core product identifiers
            $q->where(function ($fullQ) use ($term) {
                $fullQ->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('sku', 'LIKE', "%{$term}%")
                    ->orWhere('barcode', 'LIKE', "%{$term}%")
                    ->orWhere('brand', 'LIKE', "%{$term}%")
                    ->orWhere('model', 'LIKE', "%{$term}%")
                    ->orWhere('short_description', 'LIKE', "%{$term}%")
                    ->orWhere('description', 'LIKE', "%{$term}%");
            });

            // Multi-term token matching across product attributes & variant specs
            $q->orWhere(function ($multiQ) use ($tokens, $mode) {
                foreach ($tokens as $token) {
                    $token = trim($token);
                    if (empty($token)) {
                        continue;
                    }

                    $method = ($mode === 'or') ? 'orWhere' : 'where';
                    $multiQ->$method(function ($fieldQ) use ($token) {
                        $fieldQ->where('name', 'LIKE', "%{$token}%")
                            ->orWhere('sku', 'LIKE', "%{$token}%")
                            ->orWhere('barcode', 'LIKE', "%{$token}%")
                            ->orWhere('brand', 'LIKE', "%{$token}%")
                            ->orWhere('model', 'LIKE', "%{$token}%")
                            ->orWhere('material', 'LIKE', "%{$token}%")
                            ->orWhere('fabric', 'LIKE', "%{$token}%")
                            ->orWhere('supplier_name', 'LIKE', "%{$token}%")
                            ->orWhere('supplier_sku', 'LIKE', "%{$token}%")
                            ->orWhere('internal_reference', 'LIKE', "%{$token}%")
                            ->orWhereHas('variants', function ($vq) use ($token) {
                                $vq->where('sku', 'LIKE', "%{$token}%")
                                    ->orWhere('barcode', 'LIKE', "%{$token}%")
                                    ->orWhere('color', 'LIKE', "%{$token}%")
                                    ->orWhere('size', 'LIKE', "%{$token}%");
                            })
                            ->orWhereHas('categories', function ($cq) use ($token) {
                                $cq->where('name', 'LIKE', "%{$token}%")
                                    ->orWhere('slug', 'LIKE', "%{$token}%");
                            })
                            ->orWhereHas('collections', function ($colq) use ($token) {
                                $colq->where('name', 'LIKE', "%{$token}%")
                                    ->orWhere('slug', 'LIKE', "%{$token}%");
                            });
                    });
                }
            });
        });
    }

    public function scopeSearchRetailOr(Builder $query, string $term): Builder
    {
        return $this->scopeSearchRetail($query, $term, 'or');
    }

    public function getGrossMarginAttribute(): float
    {
        $price = (float) ($this->price ?: 0);
        $cost = (float) ($this->cost_price ?: 0);
        return ($price > 0 && $cost > 0) ? round($price - $cost, 2) : 0.0;
    }

    public function getGrossMarginPercentAttribute(): float
    {
        $price = (float) ($this->price ?: 0);
        $margin = $this->gross_margin;
        return ($price > 0 && $margin > 0) ? round(($margin / $price) * 100, 1) : 0.0;
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (int) $this->variants->sum('stock_quantity');
        }

        if ($this->variants()->exists()) {
            return (int) $this->variants()->sum('stock_quantity');
        }

        return (int) ($this->quantity ?: 0);
    }

    public function getFormattedStockAttribute(): string
    {
        $total = $this->total_stock;
        $variantsCount = $this->relationLoaded('variants') ? $this->variants->count() : $this->variants()->count();

        if ($variantsCount > 0) {
            return "{$total} units ({$variantsCount} variants)";
        }

        if ($total > 3) {
            return "{$total} available";
        } elseif ($total > 0) {
            return "Low Stock ({$total} left)";
        }

        return "Out of Stock";
    }

    public function getPriceNprAttribute(): ?float
    {
        if (isset($this->attributes['price_npr']) && $this->attributes['price_npr'] !== null) {
            return (float)$this->attributes['price_npr'];
        }
        return isset($this->attributes['price']) ? (float)$this->attributes['price'] : null;
    }

    public function setPriceNprAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
        $this->attributes['price_npr'] = $value;
    }

    public function getCostPriceNprAttribute(): ?float
    {
        if (isset($this->attributes['cost_price_npr']) && $this->attributes['cost_price_npr'] !== null) {
            return (float)$this->attributes['cost_price_npr'];
        }
        return isset($this->attributes['cost_price']) ? (float)$this->attributes['cost_price'] : null;
    }

    public function setCostPriceNprAttribute(mixed $value): void
    {
        $this->attributes['cost_price'] = $value;
        $this->attributes['cost_price_npr'] = $value;
    }

    public function getCompareAtPriceNprAttribute(): ?float
    {
        if (isset($this->attributes['compare_at_price_npr']) && $this->attributes['compare_at_price_npr'] !== null) {
            return (float)$this->attributes['compare_at_price_npr'];
        }
        return isset($this->attributes['compare_at_price']) ? (float)$this->attributes['compare_at_price'] : null;
    }

    public function setCompareAtPriceNprAttribute(mixed $value): void
    {
        $this->attributes['compare_at_price'] = $value;
        $this->attributes['compare_at_price_npr'] = $value;
    }
}
