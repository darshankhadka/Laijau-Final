<?php

namespace App\Models\Hrm;

use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $table = 'hrm_payroll_runs';

    protected $fillable = [
        'run_number',
        'fiscal_year',
        'name',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'total_basic_salary',
        'total_allowances',
        'total_overtime_amount',
        'total_ssf_employee',
        'total_ssf_employer',
        'total_tds_tax',
        'total_net_salary',
        'total_employer_cost',
        'total_gross_salary_npr',
        'total_pension_employee_npr',
        'total_pension_employer_npr',
        'total_net_payout_npr',
        'total_employer_cost_npr',
        'journal_entry_id',
        'payout_journal_entry_id',
        'payout_bank_account_id',
        'payout_date',
        'payout_reference',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'payout_date' => 'date',
        'total_basic_salary' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_overtime_amount' => 'decimal:2',
        'total_ssf_employee' => 'decimal:2',
        'total_ssf_employer' => 'decimal:2',
        'total_tds_tax' => 'decimal:2',
        'total_net_salary' => 'decimal:2',
        'total_employer_cost' => 'decimal:2',
        'total_gross_salary_npr' => 'decimal:2',
        'total_pension_employee_npr' => 'decimal:2',
        'total_pension_employer_npr' => 'decimal:2',
        'total_net_payout_npr' => 'decimal:2',
        'total_employer_cost_npr' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PayrollRun $run) {
            if (in_array($run->status, ['approved', 'posted_to_accounting', 'paid'], true)) {
                throw new \RuntimeException("Finalized or disbursed payroll runs (#{$run->run_number}) are statutory records and cannot be deleted.");
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function payoutJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'payout_journal_entry_id');
    }

    public function payoutBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'payout_bank_account_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getGrossSalaryAttribute(): float
    {
        return (float)($this->total_basic_salary > 0
            ? ($this->total_basic_salary + $this->total_allowances + $this->total_overtime_amount)
            : $this->total_gross_salary_npr);
    }

    public function getNetSalaryAttribute(): float
    {
        return (float)($this->total_net_salary > 0 ? $this->total_net_salary : $this->total_net_payout_npr);
    }
}
