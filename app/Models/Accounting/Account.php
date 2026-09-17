<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'account_number',
        'name',
        'category',
        'account_type',
        'normal_balance',
        'default_vat_rate',
        'currency',
        'is_active',
        'is_system',
        'description',
        'current_balance',
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2',
        'current_balance' => 'decimal:4',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->account_number} - {$this->name}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Recalculates live balance from posted, non-reversed journal lines.
     */
    public function recalculateBalance(): float
    {
        $lines = $this->journalEntryLines()
            ->whereHas('journalEntry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = (float)($lines->total_debit ?? 0);
        $totalCredit = (float)($lines->total_credit ?? 0);

        $newBalance = $this->normal_balance === 'credit'
            ? ($totalCredit - $totalDebit)
            : ($totalDebit - $totalCredit);

        $this->update(['current_balance' => round($newBalance, 4)]);

        return $newBalance;
    }
}
