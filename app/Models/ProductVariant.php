<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'size',
        'color',
        'color_hex',
        'price',
        'price_npr',
        'cost_price',
        'cost_price_npr',
        'wholesale_price',
        'stock_quantity',
        'reserved_quantity',
        'weight',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            if (empty($variant->sku)) {
                $variant->sku = app(\App\Services\ProductSkuService::class)->generateForVariant($variant);
            }
            if (empty($variant->barcode)) {
                $variant->barcode = app(\App\Services\ProductSkuService::class)->generateBarcodeForVariant($variant);
            }
        });

        static::updating(function (ProductVariant $variant) {
            if ($variant->isDirty('sku') && empty($variant->sku) && !empty($variant->getOriginal('sku'))) {
                $variant->sku = $variant->getOriginal('sku');
            }
        });

        static::saving(function (ProductVariant $variant) {
            $parent = $variant->product ?? ($variant->product_id ? Product::find($variant->product_id) : null);

            // Inherit NPR prices from parent product if not explicitly set on variant
            if (empty($variant->price) && $parent?->price) {
                $variant->price = $parent->price;
            }
            if (empty($variant->cost_price) && $parent?->cost_price) {
                $variant->cost_price = $parent->cost_price;
            }

            $raw = $variant->attributes['image'] ?? null;
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            if (!empty($raw) && is_string($raw)) {
                if (str_contains($raw, '/storage/')) {
                    $raw = substr($raw, strpos($raw, '/storage/') + strlen('/storage/'));
                    $raw = ltrim($raw, '/\\');
                }
                if ($variant->isDirty('image')) {
                    $optimized = \App\Services\ImageOptimizerService::optimizeStoragePath($raw);
                    $variant->attributes['image'] = $optimized ?: $raw;
                } else {
                    $variant->attributes['image'] = $raw;
                }
            }
        });

        static::saved(function (ProductVariant $variant) {
            if ($variant->wasChanged(['stock_quantity', 'cost_price', 'price'])) {
                try {
                    $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                    $defaultWh = $inventoryService->getDefaultWarehouse();

                    \App\Models\Inventory\StockLevel::updateOrCreate(
                        [
                            'warehouse_id' => $defaultWh->id,
                            'product_id' => $variant->product_id,
                            'variant_id' => $variant->id,
                        ],
                        [
                            'quantity_on_hand' => (int)$variant->stock_quantity,
                            'unit_cost_npr' => (float)($variant->cost_price ?: $variant->price ?: 0),
                        ]
                    );

                    // Keep parent product stock quantity synchronized with sum of all variant stock
                    $parent = Product::find($variant->product_id);
                    if ($parent && $parent->variants()->exists()) {
                        $parent->updateQuietly([
                            'quantity' => (int)$parent->variants()->sum('stock_quantity')
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Suppress during early migration setup if table not yet migrated
                }
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageAttribute($value): ?string
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

    public function stockLevels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Inventory\StockLevel::class, 'variant_id');
    }

    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock_quantity - $this->reserved_quantity);
    }

    public function getGrossMarginAttribute(): float
    {
        $price = (float) ($this->price ?: ($this->product?->price ?: 0));
        $cost = (float) ($this->cost_price ?: ($this->product?->cost_price ?: 0));
        return ($price > 0 && $cost > 0) ? round($price - $cost, 2) : 0.0;
    }

    public function getGrossMarginPercentAttribute(): float
    {
        $price = (float) ($this->price ?: ($this->product?->price ?: 0));
        $margin = $this->gross_margin;
        return ($price > 0 && $margin > 0) ? round(($margin / $price) * 100, 1) : 0.0;
    }

    public function getPriceNprAttribute(): float
    {
        return (float) ($this->attributes['price'] ?? 0.0);
    }

    public function setPriceNprAttribute($value): void
    {
        $this->attributes['price'] = $value;
    }

    public function getCostPriceNprAttribute(): float
    {
        return (float) ($this->attributes['cost_price'] ?? 0.0);
    }

    public function setCostPriceNprAttribute($value): void
    {
        $this->attributes['cost_price'] = $value;
    }
}

