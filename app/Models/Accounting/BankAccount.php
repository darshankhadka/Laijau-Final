<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'accounting_bank_accounts';

    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'reg_number',
        'iban',
        'bic_swift',
        'currency',
        'ledger_account_id',
        'opening_balance',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ledger_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function recalculateBalance(): float
    {
        $sum = (float)$this->transactions()->sum('amount');
        $newBal = round((float)$this->opening_balance + $sum, 4);
        $this->update(['current_balance' => $newBal]);
        return $newBal;
    }
}
