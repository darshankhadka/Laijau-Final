<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    public const INQUIRY_GENERAL = 'general';
    public const INQUIRY_PRODUCT = 'product_inquiry';
    public const INQUIRY_ORDER = 'order_status';
    public const INQUIRY_DELIVERY = 'delivery';
    public const INQUIRY_COMPLAINT = 'complaint';
    public const INQUIRY_BESPOKE = 'bespoke';

    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'inquiry_type',
        'customer_id',
        'order_id',
        'product_id',
        'assigned_staff_id',
        'message',
        'status',
        'priority',
        'reply_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW => 'New / Unread',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
        ];
    }

    public static function getInquiryTypes(): array
    {
        return [
            self::INQUIRY_GENERAL => 'General Question',
            self::INQUIRY_PRODUCT => 'Product / Sizing Question',
            self::INQUIRY_ORDER => 'Order Status Inquiry',
            self::INQUIRY_DELIVERY => 'Delivery / Courier Question',
            self::INQUIRY_COMPLAINT => 'Customer Complaint',
            self::INQUIRY_BESPOKE => 'Custom & Bulk Orders',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
