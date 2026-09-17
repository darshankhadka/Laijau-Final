<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $table = 'shipments';

    public const PROVIDER_NCM = 'ncm';
    public const PROVIDER_PATHAO = 'pathao';

    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_PICKUP_PENDING = 'pickup_pending';
    public const STATUS_RETURNED_TO_VENDOR = 'returned_to_vendor';
    public const STATUS_OTHER = 'other';

    public const MATCH_STATUS_MATCHED = 'matched';
    public const MATCH_STATUS_AMBIGUOUS = 'ambiguous';
    public const MATCH_STATUS_UNMATCHED = 'unmatched';
    public const MATCH_STATUS_MANUAL = 'manual';

    protected $fillable = [
        'order_id',
        'provider',
        'external_tracking_number',
        'external_reference',
        'source',
        'source_file',
        'source_created_at',
        'status',
        'normalized_status',
        'source_branch',
        'destination_branch',
        'receiver_name',
        'receiver_phone',
        'normalized_receiver_phone',
        'cod_amount',
        'delivery_charge',
        'package_description',
        'remarks',
        'weight',
        'delivered_at',
        'vendor_return',
        'created_by_source',
        'match_status',
        'match_method',
        'match_confidence',
        'match_reason',
        'matched_by',
        'matched_at',
        'raw_metadata',
    ];

    protected $casts = [
        'source_created_at' => 'datetime',
        'delivered_at' => 'datetime',
        'matched_at' => 'datetime',
        'vendor_return' => 'boolean',
        'cod_amount' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'weight' => 'decimal:2',
        'raw_metadata' => 'array',
    ];

    /**
     * Relationship with the internal Laijau Order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Staff user who manually linked this shipment if applicable.
     */
    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    /**
     * Logistics events log for this shipment's external tracking number.
     */
    public function logisticsEvents(): HasMany
    {
        return $this->hasMany(LogisticsEvent::class, 'external_order_id', 'external_tracking_number');
    }

    /**
     * Public courier tracking URL.
     */
    public function getTrackingUrlAttribute(): string
    {
        if ($this->provider === self::PROVIDER_NCM) {
            return "https://nepalcanmove.com/track?tracking_id=" . urlencode((string)$this->external_tracking_number);
        }

        if ($this->provider === self::PROVIDER_PATHAO) {
            return "https://merchant.pathao.com/tracking?consignment_id=" . urlencode((string)$this->external_tracking_number);
        }

        return '';
    }

    public function isDelivered(): bool
    {
        return $this->normalized_status === self::STATUS_DELIVERED && !$this->vendor_return;
    }

    public function isReturned(): bool
    {
        return $this->vendor_return || $this->normalized_status === self::STATUS_RETURNED_TO_VENDOR;
    }

    /* ---------------- Scopes ---------------- */

    public function scopeMatched(Builder $query): Builder
    {
        return $query->whereIn('match_status', [self::MATCH_STATUS_MATCHED, self::MATCH_STATUS_MANUAL]);
    }

    public function scopeAmbiguous(Builder $query): Builder
    {
        return $query->where('match_status', self::MATCH_STATUS_AMBIGUOUS);
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('match_status', self::MATCH_STATUS_UNMATCHED);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('normalized_status', self::STATUS_DELIVERED);
    }

    public function scopeReturned(Builder $query): Builder
    {
        return $query->where('vendor_return', true)
            ->orWhere('normalized_status', self::STATUS_RETURNED_TO_VENDOR);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('normalized_status', [
            self::STATUS_DISPATCHED,
            self::STATUS_ARRIVED,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_PICKUP_PENDING,
        ]);
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
