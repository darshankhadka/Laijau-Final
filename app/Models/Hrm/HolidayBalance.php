<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayBalance extends Model
{
    use HasFactory;

    protected $table = 'hrm_holiday_balances';

    protected $fillable = [
        'employee_id',
        'holiday_year',
        'accrued_days',
        'used_days',
        'transferred_days',
        'current_balance_days',
        'leave_allowance_accrued_npr',
        'leave_allowance_used_npr',
    ];

    protected $casts = [
        'holiday_year' => 'integer',
        'accrued_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'transferred_days' => 'decimal:2',
        'current_balance_days' => 'decimal:2',
        'leave_allowance_accrued_npr' => 'decimal:2',
        'leave_allowance_used_npr' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Recalculate remaining current balance days.
     */
    public function recalculate(): void
    {
        $this->current_balance_days = round(
            ($this->accrued_days ?? 0) + ($this->transferred_days ?? 0) - ($this->used_days ?? 0),
            2
        );
    }
}
