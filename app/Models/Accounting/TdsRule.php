<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TdsRule extends Model
{
    use HasFactory;

    protected $table = 'accounting_tds_rules';

    protected $fillable = [
        'fiscal_year',
        'payment_type',
        'section',
        'rate',
        'rate_without_pan',
        'threshold',
        'requires_pan',
        'exemptions',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'rate_without_pan' => 'decimal:2',
        'threshold' => 'decimal:4',
        'requires_pan' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function scopeForFiscalYear(Builder $query, string $fiscalYear): Builder
    {
        return $query->where('fiscal_year', $fiscalYear);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePaymentType(Builder $query, string $paymentType): Builder
    {
        return $query->where('payment_type', $paymentType);
    }

    /**
     * Resolves the active TDS rule for a given fiscal year, payment type, and transaction date.
     */
    public static function resolveRule(
        string $fiscalYear,
        string $paymentType,
        string|Carbon|null $date = null
    ): ?self {
        $targetDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');

        return self::where('fiscal_year', $fiscalYear)
            ->where('payment_type', $paymentType)
            ->where('is_active', true)
            ->where('effective_from', '<=', $targetDate)
            ->where(function (Builder $query) use ($targetDate) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $targetDate);
            })
            ->latest('effective_from')
            ->first()
            ?: self::where('payment_type', $paymentType)
                ->where('is_active', true)
                ->latest('effective_from')
                ->first();
    }
}
