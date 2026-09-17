<?php

namespace App\Models\Inventory;

use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'inventory_purchase_orders';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'currency',
        'exchange_rate_to_npr',
        'subtotal_currency',
        'shipping_cost_npr',
        'customs_duty_npr',
        'total_amount_npr',
        'status',
        'journal_entry_id',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'approved_at' => 'datetime',
        'exchange_rate_to_npr' => 'decimal:6',
        'subtotal_currency' => 'decimal:2',
        'shipping_cost_npr' => 'decimal:2',
        'customs_duty_npr' => 'decimal:2',
        'total_amount_npr' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\AccountingInvoice::class, 'reference_purchase_order_id');
    }

    public function kharidKhata(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\KharidKhataEntry::class, 'reference_purchase_order_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function generateNextPoNumber(): string
    {
        $poPrefix = 'PO-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $poPrefix = app(\App\Services\Settings\SettingsService::class)->getString('purchasing', 'auto_po_numbering_prefix', 'PO-');
        }
        $poPrefix = rtrim($poPrefix, '-') . '-';
        $month = date('Y-m');
        $prefix = "{$poPrefix}{$month}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "purchase_order_{$month}",
            $prefix,
            3,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('inventory_purchase_orders', 'po_number', $p)
        );
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0.00;
        foreach ($this->items as $item) {
            $subtotal += (float)$item->total_cost_npr;
        }

        $this->subtotal_currency = $subtotal;
        $this->total_amount_npr = round($subtotal + (float)$this->shipping_cost_npr + (float)$this->customs_duty_npr, 2);
    }
}
