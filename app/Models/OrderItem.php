<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'sku',
        'quantity',
        'unit_price',
        'selected_color',
        'selected_size',
        'custom_measurements',
        'is_preorder',
        'preorder_dispatch_note',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'is_preorder' => 'boolean',
        'custom_measurements' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getSizeAttribute(): ?string
    {
        return $this->selected_size;
    }

    public function getColorAttribute(): ?string
    {
        return $this->selected_color;
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) ($this->unit_price * $this->quantity);
    }
}
