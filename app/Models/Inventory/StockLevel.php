<?php

namespace App\Models\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_levels';

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_incoming',
        'damaged_quantity',
        'quarantined_quantity',
        'reorder_point',
        'reorder_quantity',
        'safety_stock',
        'maximum_stock',
        'unit_cost_npr',
        'bin_location',
        'last_counted_at',
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_incoming' => 'integer',
        'damaged_quantity' => 'integer',
        'quarantined_quantity' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'safety_stock' => 'integer',
        'maximum_stock' => 'integer',
        'unit_cost_npr' => 'decimal:2',
        'last_counted_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getQuantityAvailableAttribute(): int
    {
        return max(0, (int)$this->quantity_on_hand - (int)$this->quantity_reserved - (int)$this->quarantined_quantity - (int)$this->damaged_quantity);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->getQuantityAvailableAttribute();
    }

    public function getReservedQuantityAttribute(): int
    {
        return (int)$this->quantity_reserved;
    }

    public function getTotalValuationNprAttribute(): float
    {
        return round($this->quantity_on_hand * (float)$this->unit_cost_npr, 2);
    }

    public function isLowStock(): bool
    {
        return $this->quantity_available <= $this->reorder_point;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_available <= 0;
    }

    public function isOverStock(): bool
    {
        return $this->maximum_stock > 0 && $this->quantity_on_hand > $this->maximum_stock;
    }
}
