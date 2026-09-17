<?php

namespace App\Models\Inventory;

use App\Models\Accounting\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_movements';

    protected $fillable = [
        'movement_number',
        'warehouse_id',
        'target_warehouse_id',
        'product_id',
        'variant_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'available_before',
        'available_after',
        'unit_cost_npr',
        'total_cost_npr',
        'currency',
        'reference_type',
        'reference_id',
        'reference_number',
        'journal_entry_id',
        'user_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'available_before' => 'integer',
        'available_after' => 'integer',
        'unit_cost_npr' => 'decimal:2',
        'total_cost_npr' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Stock movements are immutable audit records and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Stock movements are immutable audit records and cannot be deleted.');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function generateNextMovementNumber(): string
    {
        $day = date('Ymd');
        $prefix = "MOV-{$day}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "stock_movement_{$day}",
            $prefix,
            4,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_movements', 'movement_number', $p)
        );
    }
}
