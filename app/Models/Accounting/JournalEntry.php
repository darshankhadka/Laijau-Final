<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'entry_number',
        'voucher_date',
        'accounting_period_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'description',
        'currency',
        'exchange_rate_to_npr',
        'total_debit',
        'total_credit',
        'is_balanced',
        'status',
        'reversed_by_entry_id',
        'reversal_of_entry_id',
        'reversal_reason',
        'created_by',
        'posted_at',
        'notes',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'exchange_rate_to_npr' => 'decimal:6',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'is_balanced' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('line_number');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->period();
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function reversalOfEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_entry_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (JournalEntry $entry) {
            if ($entry->status === 'posted') {
                throw new \RuntimeException("Statutory Compliance Violation: Posted journal voucher #{$entry->entry_number} is immutable and cannot be deleted. Please post a reversal journal voucher.");
            }
        });
    }

    public static function generateNextEntryNumber(?string $date = null): string
    {
        $year = $date ? substr($date, 0, 4) : date('Y');
        $voucherPrefix = 'JV-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $voucherPrefix = app(\App\Services\Settings\SettingsService::class)->getString('accounting', 'journal_voucher_prefix', 'JV-');
        }
        $voucherPrefix = rtrim($voucherPrefix, '-') . '-';
        $prefix = "{$voucherPrefix}{$year}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "journal_entry_{$year}",
            $prefix,
            4,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('accounting_journal_entries', 'entry_number', $p)
        );
    }

    public function recalculateTotals(): void
    {
        $totals = $this->lines()
            ->selectRaw('COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
            ->first();

        $debit = round((float)($totals->sum_debit ?? 0), 4);
        $credit = round((float)($totals->sum_credit ?? 0), 4);
        $isBalanced = abs($debit - $credit) < 0.005;

        $this->update([
            'total_debit' => $debit,
            'total_credit' => $credit,
            'is_balanced' => $isBalanced,
        ]);
    }
}
