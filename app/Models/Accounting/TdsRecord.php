<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TdsRecord extends Model
{
    use HasFactory;

    protected $table = 'accounting_tds_records';

    protected $fillable = [
        'tds_number',
        'fiscal_year',
        'payee_name',
        'payee_pan',
        'payment_type',
        'gross_amount',
        'tds_rate',
        'tds_amount',
        'transaction_date',
        'deposit_status',
        'ird_voucher_no',
        'ird_challan_no',
        'deposited_at',
        'journal_entry_id',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'tds_rate' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'deposited_at' => 'date',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public static function generateNextTdsNumber(string $fiscalYear): string
    {
        $cleanFy = str_replace('/', '', $fiscalYear);
        $count = self::where('fiscal_year', $fiscalYear)->count() + 1;
        return sprintf('TDS-%s-%04d', $cleanFy, $count);
    }

    public function getPaymentTypeLabelAttribute(): string
    {
        return match ($this->payment_type) {
            'rent' => 'House / Showroom Rent (10%)',
            'contract_goods' => 'Supply of Goods / Contract (1.5%)',
            'consultancy' => 'Professional / Consultancy (15%)',
            'transport_freight' => 'Transport / Courier Freight (2.5%)',
            'salary' => 'Employment Salary Withholding',
            'commission' => 'Agency / Sales Commission (15%)',
            'interest' => 'Bank / Loan Interest (5%)',
            default => ucfirst(str_replace('_', ' ', (string)$this->payment_type)),
        };
    }
}
