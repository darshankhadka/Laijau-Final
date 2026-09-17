<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    use HasFactory;

    protected $table = 'hrm_employment_contracts';

    protected $fillable = [
        'employee_id',
        'contract_type',
        'start_date',
        'end_date',
        'weekly_hours',
        'monthly_salary_npr',
        'hourly_rate_npr',
        'pension_employer_rate',
        'pension_employee_rate',
        'ssf_type',
        'festival_allowance_rate',
        'has_paid_lunch_break',
        'terms_text',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weekly_hours' => 'decimal:2',
        'monthly_salary_npr' => 'decimal:2',
        'hourly_rate_npr' => 'decimal:2',
        'pension_employer_rate' => 'decimal:2',
        'pension_employee_rate' => 'decimal:2',
        'festival_allowance_rate' => 'decimal:2',
        'has_paid_lunch_break' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
