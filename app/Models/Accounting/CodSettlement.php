<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodSettlement extends Model
{
    use HasFactory;

    protected $table = 'accounting_cod_settlements';

    protected $fillable = [
        'settlement_number',
        'courier_name',
        'settlement_date',
        'settlement_reference',
        'total_order_amount',
        'courier_fee',
        'net_bank_deposited',
        'bank_account_id',
        'journal_entry_id',
        'status',
        'reconciled_order_ids',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_order_amount' => 'decimal:2',
        'courier_fee' => 'decimal:2',
        'net_bank_deposited' => 'decimal:2',
        'reconciled_order_ids' => 'array',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public static function generateNextSettlementNumber(): string
    {
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return sprintf('COD-%s-%04d', $year, $count);
    }
}
