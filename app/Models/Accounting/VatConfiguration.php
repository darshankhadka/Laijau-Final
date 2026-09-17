<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatConfiguration extends Model
{
    use HasFactory;

    protected $table = 'accounting_vat_configurations';

    protected $fillable = [
        'fiscal_year',
        'standard_vat_rate',
        'taxable_eligible',
        'zero_rated_export_eligible',
        'exempt_schedule_eligible',
        'input_vat_restricted_categories',
        'filing_frequency',
        'effective_from',
        'effective_until',
        'notes',
    ];

    protected $casts = [
        'standard_vat_rate' => 'decimal:2',
        'taxable_eligible' => 'boolean',
        'zero_rated_export_eligible' => 'boolean',
        'exempt_schedule_eligible' => 'boolean',
        'input_vat_restricted_categories' => 'array',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function scopeForFiscalYear(Builder $query, string $fiscalYear): Builder
    {
        return $query->where('fiscal_year', $fiscalYear);
    }

    /**
     * Resolves the VAT configuration for a given fiscal year and optional date.
     */
    public static function resolveForFiscalYear(string $fiscalYear, string|Carbon|null $date = null): self
    {
        $targetDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');

        $config = self::where('fiscal_year', $fiscalYear)
            ->where('effective_from', '<=', $targetDate)
            ->where(function (Builder $query) use ($targetDate) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $targetDate);
            })
            ->latest('effective_from')
            ->first()
            ?: self::where('fiscal_year', $fiscalYear)->first();

        if ($config) {
            return $config;
        }

        // Return a default statutory instance if unseeded
        return new self([
            'fiscal_year' => $fiscalYear,
            'standard_vat_rate' => 13.00,
            'taxable_eligible' => true,
            'zero_rated_export_eligible' => true,
            'exempt_schedule_eligible' => true,
            'filing_frequency' => 'monthly',
            'effective_from' => '2024-07-16',
        ]);
    }
}
