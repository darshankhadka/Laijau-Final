<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockCount extends Model
{
    use HasFactory;

    protected $table = 'inventory_stock_counts';

    protected $fillable = [
        'count_number',
        'warehouse_id',
        'count_type',
        'count_date',
        'status',
        'total_expected_items',
        'total_counted_items',
        'total_variance_items',
        'total_variance_value_npr',
        'conducted_by',
        'approved_by',
        'approved_at',
        'reconciled_by',
        'reconciled_at',
        'notes',
    ];

    protected $casts = [
        'count_date' => 'date',
        'approved_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'total_expected_items' => 'integer',
        'total_counted_items' => 'integer',
        'total_variance_items' => 'integer',
        'total_variance_value_npr' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function conductedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_id');
    }

    public static function generateNextCountNumber(): string
    {
        $month = date('Y-m');
        $prefix = "CNT-{$month}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "stock_count_{$month}",
            $prefix,
            3,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_stock_counts', 'count_number', $p)
        );
    }

    public function recalculateTotals(): void
    {
        $expected = (int)$this->items()->sum('expected_quantity');
        $counted = (int)$this->items()->sum('counted_quantity');
        $variance = (int)$this->items()->sum('variance_quantity');
        $varianceValue = (float)$this->items()->sum('variance_value_npr');

        $this->updateQuietly([
            'total_expected_items' => $expected,
            'total_counted_items' => $counted,
            'total_variance_items' => $variance,
            'total_variance_value_npr' => $varianceValue,
        ]);
    }
}
