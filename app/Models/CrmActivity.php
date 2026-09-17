<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    use HasFactory;

    public const TYPE_STAGE_CHANGE = 'stage_change';
    public const TYPE_NOTE = 'note';
    public const TYPE_CALL = 'call';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_CONVERSION = 'conversion';
    public const TYPE_ORDER_LINKED = 'order_linked';

    protected $fillable = [
        'crm_lead_id',
        'user_id',
        'type',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getStaffIdAttribute(): ?int
    {
        return $this->user_id;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_STAGE_CHANGE => 'Stage Changed',
            self::TYPE_NOTE => 'Internal Note',
            self::TYPE_CALL => 'Phone Call',
            self::TYPE_WHATSAPP => 'WhatsApp Outreach',
            self::TYPE_FOLLOW_UP => 'Follow-up Scheduled',
            self::TYPE_CONVERSION => 'Lead Converted',
            self::TYPE_ORDER_LINKED => 'Order Created',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_STAGE_CHANGE => 'heroicon-m-arrows-right-left',
            self::TYPE_NOTE => 'heroicon-m-document-text',
            self::TYPE_CALL => 'heroicon-m-phone',
            self::TYPE_WHATSAPP => 'heroicon-m-chat-bubble-left-right',
            self::TYPE_FOLLOW_UP => 'heroicon-m-calendar',
            self::TYPE_CONVERSION => 'heroicon-m-check-badge',
            self::TYPE_ORDER_LINKED => 'heroicon-m-shopping-bag',
            default => 'heroicon-m-information-circle',
        };
    }
}
