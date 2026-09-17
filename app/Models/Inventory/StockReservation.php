<?php

namespace App\Models\Inventory;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use HasFactory;

    protected $table = 'inventory_reservations';

    protected $fillable = [
        'reservation_number',
        'reference_type',
        'reference_id',
        'cart_token',
        'warehouse_id',
        'product_id',
        'variant_id',
        'quantity',
        'status',
        'expires_at',
        'released_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public static function generateNextReservationNumber(): string
    {
        $day = date('Ymd');
        $prefix = "RES-{$day}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "stock_reservation_{$day}",
            $prefix,
            4,
            fn ($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_stock_reservations', 'reservation_number', $p)
        );
    }
}
