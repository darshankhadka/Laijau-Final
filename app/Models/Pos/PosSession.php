<?php

namespace App\Models\Pos;

use App\Models\Hardware\PosStation;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasFactory;

    protected $table = 'pos_sessions';

    protected $fillable = [
        'pos_station_id',
        'warehouse_id',
        'terminal_code',
        'terminal_name',
        'showroom_name',
        'business_date',
        'status',
        'opening_balance',
        'opening_notes',
        'opened_by_user_id',
        'opened_by_name',
        'opened_at',
        'expected_cash',
        'closing_cash_counted',
        'denominations',
        'cash_variance',
        'variance_reason_code',
        'variance_reason_text',
        'cash_sales',
        'digital_sales',
        'cash_in',
        'cash_out',
        'change_given',
        'total_sales_amount',
        'total_sales_count',
        'total_units_sold',
        'total_discount_amount',
        'total_void_amount',
        'total_void_count',
        'payment_breakdown',
        'closed_by_user_id',
        'closed_by_name',
        'closed_at',
        'closing_notes',
        'manager_name',
        'manager_signed_at',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'opening_balance' => 'float',
        'expected_cash' => 'float',
        'closing_cash_counted' => 'float',
        'denominations' => 'array',
        'cash_variance' => 'float',
        'cash_sales' => 'float',
        'digital_sales' => 'float',
        'cash_in' => 'float',
        'cash_out' => 'float',
        'change_given' => 'float',
        'total_sales_amount' => 'float',
        'total_sales_count' => 'integer',
        'total_units_sold' => 'integer',
        'total_discount_amount' => 'float',
        'total_void_amount' => 'float',
        'total_void_count' => 'integer',
        'payment_breakdown' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'manager_signed_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(PosStation::class, 'pos_station_id');
    }

    public function showroom(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(OfflineSale::class, 'pos_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosingRequired(): bool
    {
        return $this->status === 'closing_required';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
