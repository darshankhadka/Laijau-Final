<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    use HasFactory;

    protected $table = 'inventory_transfers';

    protected $fillable = [
        'transfer_number',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'initiated_by',
        'approved_by',
        'approved_at',
        'dispatched_by',
        'received_by',
        'sent_at',
        'received_at',
        'tracking_reference',
        'carrier',
        'notes',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'transfer_id');
    }

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public static function generateNextTransferNumber(): string
    {
        $trfPrefix = 'TRF-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $trfPrefix = app(\App\Services\Settings\SettingsService::class)->getString('inventory', 'stock_transfer_prefix', 'TRF-');
        }
        $trfPrefix = rtrim($trfPrefix, '-') . '-';
        $month = date('Y-m');
        $prefix = "{$trfPrefix}{$month}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "stock_transfer_{$month}",
            $prefix,
            3,
            fn ($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_transfers', 'transfer_number', $p)
        );
    }
}
