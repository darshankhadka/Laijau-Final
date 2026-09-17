<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class OfflineSale extends Model
{
    use HasFactory;

    protected $table = 'offline_sales';

    protected $fillable = [
        'sale_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'warehouse_id',
        'cash_received',
        'change_given',
        'currency',
        'exchange_rate_to_npr',
        'subtotal',
        'discount_amount',
        'discount_reason',
        'shipping_amount',
        'total_amount',
        'total_cost_npr',
        'total_profit_npr',
        'margin_percentage',
        'profit_status',
        'payment_method',
        'sales_channel',
        'status',
        'sold_at',
        'created_by',
        'staff_name',
        'customer_notes',
        'internal_notes',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
            'exchange_rate_to_npr' => 'decimal:6',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'change_given' => 'decimal:2',
            'total_cost_npr' => 'decimal:4',
            'total_profit_npr' => 'decimal:4',
            'margin_percentage' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfflineSaleItem::class, 'offline_sale_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function voidLogs(): HasMany
    {
        return $this->hasMany(OfflineSaleVoidLog::class, 'offline_sale_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\AccountingInvoice::class, 'reference_offline_sale_id');
    }

    public function bikriKhata(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\BikriKhataEntry::class, 'reference_offline_sale_id');
    }

    /**
     * Generate sequential, collision-safe sale number: OFF-000001
     */
    public static function generateNextSaleNumber(): string
    {
        $prefix = 'OFF-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $prefix = app(\App\Services\Settings\SettingsService::class)->getString('pos', 'receipt_prefix', 'OFF-');
        }
        $prefix = rtrim($prefix, '-') . '-';

        return app(\App\Services\DocumentSequenceService::class)->next(
            'offline_sale',
            $prefix,
            6,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('offline_sales', 'sale_number', $p)
        );
    }

    /**
     * Scope for active (non-voided) sales.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Channel display mapping.
     */
    public static function getChannelOptions(): array
    {
        return [
            'physical' => '📍 Direct In-Person',
            'instagram' => '📸 Instagram DM / Sale',
            'whatsapp' => '💬 WhatsApp Direct Sale',
            'showroom' => '🏛️ Laijau Showroom',
            'event' => '🎪 Pop-up & Cultural Event',
            'wholesale' => '📦 Wholesale / Direct Partner',
            'referral' => '🤝 Personal Referral',
            'other' => '🏷️ Other Offline Channel',
        ];
    }

    /**
     * Payment method display mapping.
     */
    public static function getPaymentMethodOptions(): array
    {
        return [
            'cash' => '💵 Cash Payment',
            'card' => '💳 Card Terminal (POS)',
            'esewa' => '📱 eSewa / Digital Wallet',
            'khalti' => '📱 Khalti / Fonepay QR',
            'bank_transfer' => '🏦 Bank Wire / ConnectIPS',
            'other' => '🪙 Other Payment Method',
        ];
    }

    public function getTotalCostNprAttribute(): float
    {
        return (float) ($this->attributes['total_cost_npr'] ?? 0.0);
    }

    public function setTotalCostNprAttribute($value): void
    {
        $this->attributes['total_cost_npr'] = $value;
    }

    public function getTotalProfitNprAttribute(): float
    {
        return (float) ($this->attributes['total_profit_npr'] ?? 0.0);
    }

    public function setTotalProfitNprAttribute($value): void
    {
        $this->attributes['total_profit_npr'] = $value;
    }
}
