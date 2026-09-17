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

class StockAdjustment extends Model
{
    use HasFactory;

    protected $table = 'inventory_adjustments';

    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'product_id',
        'variant_id',
        'type',
        'quantity',
        'unit_cost_npr',
        'total_value_npr',
        'reason',
        'status',
        'journal_entry_id',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost_npr' => 'decimal:2',
        'total_value_npr' => 'decimal:2',
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

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNextAdjustmentNumber(): string
    {
        $adjPrefix = 'ADJ-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $adjPrefix = app(\App\Services\Settings\SettingsService::class)->getString('inventory', 'stock_adjustment_prefix', 'ADJ-');
        }
        $adjPrefix = rtrim($adjPrefix, '-') . '-';
        $month = date('Y-m');
        $prefix = "{$adjPrefix}{$month}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "stock_adjustment_{$month}",
            $prefix,
            3,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_adjustments', 'adjustment_number', $p)
        );
    }
}
