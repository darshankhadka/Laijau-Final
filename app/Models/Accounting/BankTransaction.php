<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasFactory;

    protected $table = 'accounting_bank_transactions';

    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'value_date',
        'amount',
        'currency',
        'description',
        'external_reference',
        'is_reconciled',
        'reconciled_at',
        'reconciled_by',
        'journal_entry_id',
        'match_type',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:4',
        'is_reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function markReconciled(?int $journalEntryId = null, ?string $matchType = null, ?User $user = null): void
    {
        $this->update([
            'is_reconciled' => true,
            'reconciled_at' => now(),
            'reconciled_by' => $user?->id ?? auth()->id(),
            'journal_entry_id' => $journalEntryId ?? $this->journal_entry_id,
            'match_type' => $matchType ?? $this->match_type,
        ]);

        $this->bankAccount?->recalculateBalance();
    }
}
