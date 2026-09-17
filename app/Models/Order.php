<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    // Operational Order Lifecycles (Decoupled from Payment)
    public const STATUS_PENDING = 'pending';
    public const STATUS_CUSTOMER_CONTACT_REQUIRED = 'customer_contact_required';
    public const STATUS_CUSTOMER_CONFIRMED = 'customer_confirmed';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAYMENT_VERIFIED = 'payment_verified';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PACKING = 'packing';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_HANDED_TO_COURIER = 'handed_to_courier';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';

    // Terminal States
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURN_REQUESTED = 'return_requested';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_FAILED_DELIVERY = 'failed_delivery';

    // Legacy backwards compatibility aliases
    public const STATUS_PENDING_PAYMENT = 'pending';
    public const STATUS_PAID = 'payment_verified';
    public const STATUS_QUALITY_CHECK = 'packing';
    public const STATUS_READY_TO_SHIP = 'ready_for_delivery';
    public const STATUS_SHIPPED = 'handed_to_courier';
    public const STATUS_REFUNDED = 'returned';
    public const STATUS_PAYMENT_FAILED = 'failed_delivery';

    // Payment Statuses
    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_VERIFICATION_PENDING = 'payment_verification_pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_REJECTED = 'rejected';

    // Sales Channels
    public const CHANNEL_ONLINE = 'online';
    public const CHANNEL_POS = 'pos';
    public const CHANNEL_MANUAL = 'manual';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public static function getChannels(): array
    {
        return [
            self::CHANNEL_ONLINE => 'Online Storefront',
            self::CHANNEL_POS => 'Showroom POS',
            self::CHANNEL_MANUAL => 'Manual / Phone Order',
            self::CHANNEL_WHATSAPP => 'WhatsApp Clienteling',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_CUSTOMER_CONTACT_REQUIRED => 'Customer Contact Required',
            self::STATUS_CUSTOMER_CONFIRMED => 'Customer Confirmed',
            self::STATUS_PAYMENT_PENDING => 'Payment Pending',
            self::STATUS_PAYMENT_VERIFIED => 'Payment Verified',
            self::STATUS_PROCESSING => 'Processing Order',
            self::STATUS_PACKING => 'Packing & Boxing',
            self::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
            self::STATUS_HANDED_TO_COURIER => 'Handed to Courier',
            self::STATUS_IN_TRANSIT => 'In Transit (Nepal)',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_RETURN_REQUESTED => 'Return Requested',
            self::STATUS_RETURNED => 'Returned to Inventory',
            self::STATUS_FAILED_DELIVERY => 'Failed Delivery / Undelivered',
        ];
    }

    public static function getPaymentStatuses(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID => 'Unpaid (COD / Pending)',
            self::PAYMENT_STATUS_VERIFICATION_PENDING => 'Payment Verification Pending',
            self::PAYMENT_STATUS_PAID => 'Paid (Verified)',
            self::PAYMENT_STATUS_REJECTED => 'Payment Verification Rejected',
        ];
    }

    protected $attributes = [
        'shipping_country' => 'NP',
        'currency' => 'NPR',
        'channel' => 'online',
    ];

    protected $fillable = [
        'order_number',
        'channel',
        'guest_access_token',
        'user_id',
        'crm_lead_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alt_phone',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'province',
        'district',
        'municipality',
        'ward',
        'tole',
        'landmark',
        'is_inside_valley',
        'billing_address',
        'billing_country',
        'shipping_method',
        'shipping_fee',
        'subtotal',
        'vat_amount',
        'vat_rate',
        'coupon_discount',
        'total_amount',
        'currency',
        'status',
        'tracking_number',
        'tracking_url',
        'carrier',
        'courier_name',
        'courier_order_id',
        'courier_status',
        'courier_comments',
        'courier_last_sync_at',
        'courier_pickup_date',
        'estimated_delivery_date',
        'actual_delivery_date',
        'delivery_notes',
        'payment_method',
        'payment_status',
        'payment_id',
        'payment_reference',
        'payment_receipt_image',
        'payment_verified_at',
        'payment_verified_by',
        'payment_notes',
        'payment_rejection_reason',
        'connectips_txnid',
        'connectips_refid',
        'confirmation_email_sent_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'refunded_at',
        'refunded_amount',
        'restocking_fee',
        'internal_notes',
        'customer_notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_inside_valley' => 'boolean',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'restocking_fee' => 'decimal:2',
        'courier_pickup_date' => 'date',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'courier_last_sync_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'confirmation_email_sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public static function generateNextOrderNumber(): string
    {
        $prefix = 'LJ-';
        $padding = 4;
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $prefix = app(\App\Services\Settings\SettingsService::class)->getString('commerce', 'order_prefix', 'LJ-');
            $padding = app(\App\Services\Settings\SettingsService::class)->getInteger('commerce', 'order_padding', 4);
        }
        $prefix = rtrim($prefix, '-') . '-';
        if ($prefix === 'ORD-') {
            $padding = 5;
        }

        return app(\App\Services\DocumentSequenceService::class)->next(
            'order',
            $prefix,
            $padding,
            fn ($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('orders', 'order_number', $p)
        );
    }

    public static function generateGuestToken(): string
    {
        return Str::random(40);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function paymentVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function logisticsEvents(): HasMany
    {
        return $this->hasMany(LogisticsEvent::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function latestShipment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function ncmShipment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Shipment::class)->where('provider', Shipment::PROVIDER_NCM)->latestOfMany();
    }

    public function paymentAuditLogs(): HasMany
    {
        return $this->hasMany(PaymentAuditLog::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\AccountingInvoice::class, 'reference_order_id');
    }

    public function bikriKhata(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Accounting\BikriKhataEntry::class, 'reference_order_id');
    }

    public function getCustomerFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute(): string
    {
        $parts = [];
        if ($this->tole) $parts[] = $this->tole;
        if ($this->ward) $parts[] = 'Ward No. ' . $this->ward;
        if ($this->landmark) $parts[] = 'Near ' . $this->landmark;
        if ($this->municipality) $parts[] = $this->municipality;
        if ($this->district) $parts[] = $this->district;
        if ($this->province) $parts[] = $this->province;

        if (empty($parts)) {
            return (string) ($this->shipping_address ?: 'Kathmandu, Nepal');
        }

        return implode(', ', $parts);
    }

    public static function resolveTrackingUrl(?string $carrier, ?string $trackingNumber): ?string
    {
        if (empty($trackingNumber)) return null;

        $t = trim($trackingNumber);
        $c = strtolower(trim((string)$carrier));

        if (str_contains($c, 'ncm') || str_contains($c, 'nepal can move')) {
            return "https://nepalcanmove.com/track?tracking_id={$t}";
        }

        if (str_contains($c, 'pathao')) {
            return "https://pathao.com/np/parcel-tracking/?consignment_id={$t}";
        }

        if (str_contains($c, 'dhl')) {
            return "https://www.dhl.com/en/express/tracking.html?AWB={$t}";
        }

        return "https://nepalcanmove.com/track?tracking_id={$t}";
    }

    /**
     * Resolve the associated customer profile or fallback to phone/email lookup.
     */
    public function resolveCustomer(): ?User
    {
        if ($this->user_id && $this->user) {
            return $this->user;
        }

        if (!empty($this->phone)) {
            $clean = preg_replace('/[^0-9]/', '', (string)$this->phone);
            if (strlen($clean) >= 10) {
                $last10 = substr($clean, -10);
                $found = User::where('role', 'customer')
                    ->where(function ($q) use ($last10) {
                        $q->where('phone', 'like', "%{$last10}")
                            ->orWhere('phone', $last10);
                    })
                    ->first();
                if ($found) return $found;
            }
        }

        if (!empty($this->email) && !str_starts_with($this->email, 'guest_')) {
            $found = User::where('role', 'customer')
                ->where('email', strtolower(trim($this->email)))
                ->first();
            if ($found) return $found;
        }

        return null;
    }

    /**
     * Build chronological operational timeline of events for the order workspace.
     */
    public function getTimelineEvents(): array
    {
        $events = [];

        // 1. Order Placed
        if ($this->created_at) {
            $channelLabel = self::getChannels()[$this->channel] ?? ucfirst($this->channel ?? 'online');
            $events[] = [
                'timestamp' => $this->created_at,
                'title' => 'Order Placed',
                'description' => "Channel: {$channelLabel} | Method: " . strtoupper($this->payment_method ?? 'COD'),
                'icon' => 'heroicon-m-shopping-bag',
                'color' => 'primary',
            ];
        }

        // 2. Customer Confirmation
        if ($this->status === self::STATUS_CUSTOMER_CONFIRMED) {
            $events[] = [
                'timestamp' => $this->updated_at,
                'title' => 'Customer Confirmed',
                'description' => 'Address and order items confirmed via WhatsApp / Phone.',
                'icon' => 'heroicon-m-phone',
                'color' => 'info',
            ];
        }

        // 3. Payment Verified
        if ($this->payment_verified_at) {
            $events[] = [
                'timestamp' => $this->payment_verified_at,
                'title' => 'Payment Confirmed',
                'description' => "Amount: Rs. " . number_format((float)$this->total_amount, 2) . ($this->payment_reference ? " (Ref: {$this->payment_reference})" : ''),
                'icon' => 'heroicon-m-check-badge',
                'color' => 'success',
            ];
        }

        // 4. Payment Audit Logs
        foreach ($this->paymentAuditLogs as $log) {
            $events[] = [
                'timestamp' => $log->created_at,
                'title' => 'Payment Event: ' . ucfirst(str_replace('_', ' ', (string)$log->new_payment_status)),
                'description' => $log->notes ?: ("Transaction Ref: " . ($log->transaction_reference ?: 'N/A')),
                'icon' => 'heroicon-m-banknotes',
                'color' => $log->new_payment_status === 'paid' ? 'success' : 'warning',
            ];
        }

        // 5. Courier Handover / Dispatch
        if ($this->courier_pickup_date) {
            $events[] = [
                'timestamp' => $this->courier_pickup_date,
                'title' => 'Handed to Courier (' . ($this->carrier ?: 'Courier') . ')',
                'description' => $this->tracking_number ? "AWB / Tracking: {$this->tracking_number}" : 'Dispatched from Kathmandu warehouse',
                'icon' => 'heroicon-m-truck',
                'color' => 'info',
            ];
        }

        // 6. Logistics Events
        foreach ($this->logisticsEvents as $le) {
            $events[] = [
                'timestamp' => $le->received_at ?: $le->created_at,
                'title' => 'Courier Update: ' . ucfirst((string)($le->status ?: $le->event)),
                'description' => "Provider: " . strtoupper((string)$le->provider) . ($le->external_order_id ? " (AWB: {$le->external_order_id})" : ''),
                'icon' => 'heroicon-m-paper-airplane',
                'color' => 'info',
            ];
        }

        // 7. Delivered
        if ($this->delivered_at || $this->actual_delivery_date) {
            $events[] = [
                'timestamp' => $this->delivered_at ?: $this->actual_delivery_date,
                'title' => 'Delivered to Customer',
                'description' => 'Shipment successfully completed.',
                'icon' => 'heroicon-m-check-circle',
                'color' => 'success',
            ];
        }

        // 8. Cancelled
        if ($this->cancelled_at) {
            $events[] = [
                'timestamp' => $this->cancelled_at,
                'title' => 'Order Cancelled',
                'description' => $this->cancellation_reason ?: 'Order cancelled by staff / customer request',
                'icon' => 'heroicon-m-x-circle',
                'color' => 'danger',
            ];
        }

        // 9. Refunded
        if ($this->refunded_at) {
            $events[] = [
                'timestamp' => $this->refunded_at,
                'title' => 'Order Refunded',
                'description' => "Refunded Rs. " . number_format((float)($this->refunded_amount ?? $this->total_amount), 2),
                'icon' => 'heroicon-m-arrow-path',
                'color' => 'warning',
            ];
        }

        // Sort ascending by timestamp
        usort($events, function ($a, $b) {
            $tA = $a['timestamp'] instanceof Carbon ? $a['timestamp']->timestamp : strtotime((string)$a['timestamp']);
            $tB = $b['timestamp'] instanceof Carbon ? $b['timestamp']->timestamp : strtotime((string)$b['timestamp']);
            return $tA <=> $tB;
        });

        return $events;
    }
}

