<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'name',
        'fiscal_year',
        'nepali_month',
        'nepali_label',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'locked_at',
        'locked_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'nepali_month' => 'integer',
        'locked_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(AccountingFiscalYear::class, 'fiscal_year', 'fiscal_year');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AccountingInvoice::class, 'tax_period_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'accounting_period_id');
    }

    public function vatDeclarations(): HasMany
    {
        return $this->hasMany(VatDeclaration::class, 'period_id');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function isPostingAllowed(): bool
    {
        return $this->status === 'open';
    }

    public function lock(?User $user = null): void
    {
        $this->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $user?->id ?? auth()->id(),
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'status' => 'open',
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
