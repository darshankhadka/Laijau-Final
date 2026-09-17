<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CrmLead extends Model
{
    use HasFactory;

    // Pipeline Stages
    public const STAGE_NEW = 'new';
    public const STAGE_CONTACTED = 'contacted';
    public const STAGE_QUALIFIED = 'qualified';
    public const STAGE_QUOTATION = 'quotation';
    public const STAGE_NEGOTIATION = 'negotiation';
    public const STAGE_WON = 'won';
    public const STAGE_LOST = 'lost';

    // Backwards-compatible aliases
    public const STAGE_NEW_INQUIRY = 'new';
    public const STAGE_FOLLOW_UP = 'contacted';
    public const STAGE_CONFIRMED = 'quotation';
    public const STAGE_SHIPPED = 'won';

    // Priority levels
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';

    // Lead channels / sources
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SHOWROOM = 'showroom';
    public const CHANNEL_CONCIERGE = 'concierge_form';
    public const CHANNEL_INSTAGRAM = 'instagram';
    public const CHANNEL_PHONE = 'phone';
    public const CHANNEL_REFERRAL = 'referral';
    public const CHANNEL_WEBSITE = 'website';

    protected $fillable = [
        'title',
        'contact_name',
        'phone',
        'email',
        'channel',
        'stage',
        'estimated_value',
        'currency',
        'priority',
        'assigned_to',
        'assigned_staff_id',
        'event_date',
        'follow_up_date',
        'follow_up_notes',
        'bespoke_notes',
        'internal_notes',
        'lost_reason',
        'closed_at',
        'user_id',
        'customer_id',
        'product_id',
        'order_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'event_date' => 'date',
        'follow_up_date' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function getStages(): array
    {
        return [
            self::STAGE_NEW => 'New Inquiries',
            self::STAGE_CONTACTED => 'Contacted',
            self::STAGE_QUALIFIED => 'Qualified',
            self::STAGE_QUOTATION => 'Quotation',
            self::STAGE_NEGOTIATION => 'Negotiation',
            self::STAGE_WON => 'Won / Converted',
            self::STAGE_LOST => 'Lost / Closed',
        ];
    }

    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_URGENT => 'Urgent',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_LOW => 'Low',
        ];
    }

    public static function getChannels(): array
    {
        return [
            self::CHANNEL_WHATSAPP => 'WhatsApp Support',
            self::CHANNEL_SHOWROOM => 'Showroom Walk-in',
            self::CHANNEL_CONCIERGE => 'Web Inquiry',
            self::CHANNEL_WEBSITE => 'Website Contact Form',
            self::CHANNEL_INSTAGRAM => 'Instagram Direct',
            self::CHANNEL_PHONE => 'Private Phone Call',
            self::CHANNEL_REFERRAL => 'VIP Referral',
        ];
    }

    // --- Relationships ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'crm_lead_id')->with(['user', 'staff'])->latest();
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_WON, self::STAGE_LOST]);
    }

    public function scopeOverdueFollowUps($query)
    {
        return $query->where('follow_up_date', '<', now())
            ->whereNotIn('stage', [self::STAGE_WON, self::STAGE_LOST]);
    }

    public function scopeDueTodayFollowUps($query)
    {
        return $query->whereDate('follow_up_date', today())
            ->whereNotIn('stage', [self::STAGE_WON, self::STAGE_LOST]);
    }

    // --- Helpers & Accessors ---

    public function isOverdue(): bool
    {
        if (!$this->follow_up_date || in_array($this->stage, [self::STAGE_WON, self::STAGE_LOST], true)) {
            return false;
        }

        return $this->follow_up_date->isPast();
    }

    public function getEffectiveCustomerAttribute(): ?User
    {
        return $this->customer ?? $this->user;
    }

    public function getWhatsAppUrlAttribute(): ?string
    {
        if (empty($this->phone)) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$this->phone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '977' . $cleanPhone;
        }
        $currSymbol = 'Rs. ';

        $text = "✨ *LAIJAU — CUSTOMER CARE* ✨\n\n";
        $text .= "Namaste " . ($this->contact_name ?: 'Valued Client') . ",\n";
        $text .= "We are following up regarding your inquiry: *" . $this->title . "*.\n\n";

        if ($this->product) {
            $text .= "📦 *Product:* " . $this->product->name . " (SKU: " . $this->product->sku . ")\n";
        }
        if ($this->event_date) {
            $text .= "🗓️ *Required By:* " . $this->event_date->format('d M Y') . "\n";
        }
        if ($this->estimated_value > 0) {
            $text .= "💰 *Estimated Value:* " . $currSymbol . number_format((float)$this->estimated_value, 2) . "\n";
        }

        $text .= "\nOur customer support team in Kathmandu is at your disposal for product queries, sizing guidance, and order finalization.\n\n";
        $text .= "How may we assist you today?\n\n";
        $text .= "Warmest regards,\n*Laijau Customer Care*";

        return !empty($cleanPhone)
            ? "https://wa.me/{$cleanPhone}?text=" . urlencode($text)
            : "https://api.whatsapp.com/send?text=" . urlencode($text);
    }
}
