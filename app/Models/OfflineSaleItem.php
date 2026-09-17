<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSaleItem extends Model
{
    use HasFactory;

    protected $table = 'offline_sale_items';

    protected $fillable = [
        'offline_sale_id',
        'product_id',
        'variant_id',
        'product_name',
        'sku',
        'color',
        'size',
        'quantity',
        'website_price',
        'unit_price',
        'discount_amount',
        'total_price',
        'unit_cost_npr',
        'total_cost_npr',
        'unit_profit_npr',
        'total_profit_npr',
        'margin_percentage',
        'cost_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'website_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'unit_cost_npr' => 'decimal:4',
            'total_cost_npr' => 'decimal:4',
            'unit_profit_npr' => 'decimal:4',
            'total_profit_npr' => 'decimal:4',
            'margin_percentage' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getUnitCostNprAttribute(): float
    {
        return (float) ($this->attributes['unit_cost_npr'] ?? 0.0);
    }

    public function setUnitCostNprAttribute($value): void
    {
        $this->attributes['unit_cost_npr'] = $value;
    }

    public function getTotalCostNprAttribute(): float
    {
        return (float) ($this->attributes['total_cost_npr'] ?? 0.0);
    }

    public function setTotalCostNprAttribute($value): void
    {
        $this->attributes['total_cost_npr'] = $value;
    }

    public function getUnitProfitNprAttribute(): float
    {
        return (float) ($this->attributes['unit_profit_npr'] ?? 0.0);
    }

    public function setUnitProfitNprAttribute($value): void
    {
        $this->attributes['unit_profit_npr'] = $value;
    }

    public function getTotalProfitNprAttribute(): float
    {
        return (float) ($this->attributes['total_profit_npr'] ?? 0.0);
    }

    public function setTotalProfitNprAttribute($value): void
    {
        $this->attributes['total_profit_npr'] = $value;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(OfflineSale::class, 'offline_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
