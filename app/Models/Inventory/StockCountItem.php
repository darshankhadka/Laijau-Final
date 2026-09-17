<?php

namespace App\Models\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_count_items';

    protected $fillable = [
        'stock_count_id',
        'product_id',
        'variant_id',
        'expected_quantity',
        'counted_quantity',
        'variance_quantity',
        'unit_cost_npr',
        'variance_value_npr',
        'is_reconciled',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'counted_quantity' => 'integer',
        'variance_quantity' => 'integer',
        'unit_cost_npr' => 'decimal:2',
        'variance_value_npr' => 'decimal:2',
        'is_reconciled' => 'boolean',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
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
