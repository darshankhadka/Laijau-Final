<?php

namespace App\Models\Hrm;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ExpenseClaim extends Model
{
    use HasFactory;

    protected $table = 'hrm_expense_claims';

    protected $fillable = [
        'claim_number',
        'employee_id',
        'title',
        'expense_date',
        'category',
        'ledger_account_id',
        'gross_amount_npr',
        'vat_rate',
        'vat_amount_npr',
        'net_amount_npr',
        'receipt_path',
        'mileage_km',
        'mileage_rate_npr',
        'status',
        'journal_entry_id',
        'approved_by',
        'approved_at',
        'reimbursed_at',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'gross_amount_npr' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount_npr' => 'decimal:2',
        'net_amount_npr' => 'decimal:2',
        'mileage_km' => 'decimal:2',
        'mileage_rate_npr' => 'decimal:2',
        'approved_at' => 'datetime',
        'reimbursed_at' => 'datetime',
    ];

    public static function generateNextClaimNumber(): string
    {
        $year = date('Y');
        $claimPrefix = 'UDL-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $claimPrefix = app(\App\Services\Settings\SettingsService::class)->getString('hrm', 'expense_claim_prefix', 'UDL-');
        }
        $claimPrefix = rtrim($claimPrefix, '-') . '-';
        $prefix = "{$claimPrefix}{$year}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "expense_claim_{$year}",
            $prefix,
            4,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('hrm_expense_claims', 'claim_number', $p)
        );
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ledger_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
