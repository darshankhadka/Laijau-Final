<?php

namespace App\Models\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'inventory_purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'variant_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost_currency',
        'unit_cost_npr',
        'total_cost_npr',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_cost_currency' => 'decimal:2',
        'unit_cost_npr' => 'decimal:2',
        'total_cost_npr' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PurchaseOrderItem $item) {
            $qty = (int)($item->quantity_ordered ?: 1);
            $cost = (float)($item->unit_cost_npr ?: 0);
            $item->total_cost_npr = round($qty * $cost, 2);
        });

        static::saved(function (PurchaseOrderItem $item) {
            if ($item->purchaseOrder) {
                $item->purchaseOrder->recalculateTotals();
                $item->purchaseOrder->saveQuietly();
            }
        });

        static::deleted(function (PurchaseOrderItem $item) {
            if ($item->purchaseOrder) {
                $item->purchaseOrder->recalculateTotals();
                $item->purchaseOrder->saveQuietly();
            }
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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
