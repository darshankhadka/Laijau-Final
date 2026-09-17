<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRunItem extends Model
{
    use HasFactory;

    protected $table = 'hrm_payroll_run_items';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'contract_id',
        'basic_salary',
        'allowance_amount',
        'overtime_hours',
        'overtime_amount',
        'absent_days',
        'absence_deduction',
        'bonus_amount',
        'gross_salary',
        'ssf_employee_amount',
        'ssf_employer_amount',
        'taxable_income',
        'tds_tax_rate',
        'tds_tax_amount',
        'cit_deduction',
        'other_deductions',
        'net_salary',
        'employer_total_cost',
        'payslip_number',
        'base_salary_npr',
        'hourly_rate_npr',
        'hours_worked',
        'overtime_amount_npr',
        'bonus_amount_npr',
        'allowance_amount_npr',
        'gross_salary_npr',
        'taxable_base_npr',
        'pension_employee_npr',
        'pension_employer_npr',
        'net_salary_npr',
        'total_cost_npr',
        'is_reconciled',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'absent_days' => 'decimal:2',
        'absence_deduction' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'ssf_employee_amount' => 'decimal:2',
        'ssf_employer_amount' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'tds_tax_rate' => 'decimal:2',
        'tds_tax_amount' => 'decimal:2',
        'cit_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'employer_total_cost' => 'decimal:2',
        'base_salary_npr' => 'decimal:2',
        'hourly_rate_npr' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'overtime_amount_npr' => 'decimal:2',
        'bonus_amount_npr' => 'decimal:2',
        'allowance_amount_npr' => 'decimal:2',
        'gross_salary_npr' => 'decimal:2',
        'taxable_base_npr' => 'decimal:2',
        'pension_employee_npr' => 'decimal:2',
        'pension_employer_npr' => 'decimal:2',
        'net_salary_npr' => 'decimal:2',
        'total_cost_npr' => 'decimal:2',
        'is_reconciled' => 'boolean',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'contract_id');
    }

    public function getEffectiveGrossSalaryAttribute(): float
    {
        return (float)($this->gross_salary > 0 ? $this->gross_salary : $this->gross_salary_npr);
    }

    public function getEffectiveNetSalaryAttribute(): float
    {
        return (float)($this->net_salary > 0 ? $this->net_salary : $this->net_salary_npr);
    }
}
