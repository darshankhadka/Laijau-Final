<?php

namespace App\Services;

use App\Models\Setting;

class TaxCalculatorService
{
    /**
     * Nepal Inland Revenue Department (IRD) Statutory VAT Rate.
     */
    public const NEPAL_VAT_RATE = 13.0;

    /**
     * Map of common country names and aliases to ISO 3166-1 alpha-2 country codes.
     */
    public const COUNTRY_ALIASES = [
        'NEPAL' => 'NP',
        'NP' => 'NP',
        'KATHMANDU' => 'NP',
        'LALITPUR' => 'NP',
        'BHAKTAPUR' => 'NP',
        'POKHARA' => 'NP',
    ];

    /**
     * Normalize any country string to Nepal ('NP').
     */
    public static function normalizeCountryCode(?string $country): string
    {
        return 'NP';
    }

    /**
     * Calculate statutory Nepal VAT on a VAT-exclusive (net) taxable amount.
     * Example: Taxable NPR 800 @ 13% => VAT NPR 104, Gross NPR 904.
     *
     * @param float $taxableAmount
     * @param float $rate Default 13%
     * @return array ['taxable_amount' => float, 'vat_rate' => float, 'vat_amount' => float, 'gross_amount' => float]
     */
    public function calculateExclusive(float $taxableAmount, float $rate = self::NEPAL_VAT_RATE): array
    {
        if ($taxableAmount <= 0 || $rate <= 0) {
            return [
                'taxable_amount' => round(max(0.0, $taxableAmount), 2),
                'vat_rate' => max(0.0, $rate),
                'vat_amount' => 0.00,
                'gross_amount' => round(max(0.0, $taxableAmount), 2),
            ];
        }

        $taxable = round($taxableAmount, 2);
        $vat = round($taxable * ($rate / 100), 2);
        $gross = round($taxable + $vat, 2);

        return [
            'taxable_amount' => $taxable,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'gross_amount' => $gross,
        ];
    }

    /**
     * Calculate statutory Nepal VAT on a VAT-inclusive (gross) amount.
     * Example: Gross NPR 800 @ 13% => Net NPR 707.96, VAT NPR 92.04.
     * Formula: Net = round(gross / (1 + rate/100), 2); VAT = round(gross - Net, 2).
     *
     * @param float $grossAmount
     * @param float $rate Default 13%
     * @return array ['net_amount' => float, 'taxable_amount' => float, 'vat_rate' => float, 'vat_amount' => float, 'gross_amount' => float]
     */
    public function calculateInclusive(float $grossAmount, float $rate = self::NEPAL_VAT_RATE): array
    {
        if ($grossAmount <= 0 || $rate <= 0) {
            return [
                'net_amount' => round(max(0.0, $grossAmount), 2),
                'taxable_amount' => round(max(0.0, $grossAmount), 2),
                'vat_rate' => max(0.0, $rate),
                'vat_amount' => 0.00,
                'gross_amount' => round(max(0.0, $grossAmount), 2),
            ];
        }

        $gross = round($grossAmount, 2);
        $net = round($gross / (1 + ($rate / 100)), 2);
        $vat = round($gross - $net, 2);

        return [
            'net_amount' => $net,
            'taxable_amount' => $net,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'gross_amount' => $gross,
        ];
    }

    /**
     * Static helper for VAT-exclusive calculation.
     */
    public static function calcExclusive(float $taxableAmount, float $rate = self::NEPAL_VAT_RATE): array
    {
        return app(self::class)->calculateExclusive($taxableAmount, $rate);
    }

    /**
     * Static helper for VAT-inclusive calculation.
     */
    public static function calcInclusive(float $grossAmount, float $rate = self::NEPAL_VAT_RATE): array
    {
        return app(self::class)->calculateInclusive($grossAmount, $rate);
    }

    /**
     * Extract the net amount from a VAT-inclusive gross amount.
     */
    public static function extractNetFromInclusive(float $grossAmount, float $rate = self::NEPAL_VAT_RATE): float
    {
        return self::calcInclusive($grossAmount, $rate)['net_amount'];
    }

    /**
     * Extract the VAT portion from a VAT-inclusive gross amount.
     */
    public static function extractVatFromInclusive(float $grossAmount, float $rate = self::NEPAL_VAT_RATE): float
    {
        return self::calcInclusive($grossAmount, $rate)['vat_amount'];
    }

    /**
     * Calculate VAT on a VAT-exclusive net amount.
     */
    public static function calculateVatExclusive(float $taxableAmount, float $rate = self::NEPAL_VAT_RATE): float
    {
        return self::calcExclusive($taxableAmount, $rate)['vat_amount'];
    }

    /**
     * Calculate Nepal VAT based on subtotal.
     * Respects store tax configuration (enabled status and gross/net pricing).
     *
     * @param string $countryCode
     * @param float $subtotal
     * @param bool|null $isInclusive Explicit pricing mode override
     * @return array ['rate' => float, 'amount' => float, 'inclusive' => bool, 'taxable_amount' => float, 'gross_amount' => float, 'net_amount' => float]
     */
    public function calculate(string $countryCode, float $subtotal, ?bool $isInclusive = null): array
    {
        $vatEnabled = (bool) Setting::get('vat_enabled', true);
        $inclusive = $isInclusive ?? (bool) Setting::get('display_prices_with_vat', true);

        if (!$vatEnabled || $subtotal <= 0) {
            $amt = round(max(0.0, $subtotal), 2);
            return [
                'rate' => 0.0,
                'amount' => 0.0,
                'inclusive' => $inclusive,
                'taxable_amount' => $amt,
                'gross_amount' => $amt,
                'net_amount' => $amt,
            ];
        }

        $rate = (float) Setting::get('default_vat_rate', self::NEPAL_VAT_RATE);

        if ($inclusive) {
            $data = $this->calculateInclusive($subtotal, $rate);
            return [
                'rate' => $rate,
                'amount' => $data['vat_amount'],
                'inclusive' => true,
                'taxable_amount' => $data['taxable_amount'],
                'gross_amount' => $data['gross_amount'],
                'net_amount' => $data['net_amount'],
            ];
        }

        $data = $this->calculateExclusive($subtotal, $rate);
        return [
            'rate' => $rate,
            'amount' => $data['vat_amount'],
            'inclusive' => false,
            'taxable_amount' => $data['taxable_amount'],
            'gross_amount' => $data['gross_amount'],
            'net_amount' => $data['taxable_amount'],
        ];
    }

    /**
     * Get the statutory VAT percentage for Nepal.
     */
    public function getRate(string $countryCode = 'NP'): float
    {
        $vatEnabled = (bool) Setting::get('vat_enabled', true);
        if (!$vatEnabled) {
            return 0.0;
        }

        return (float) Setting::get('default_vat_rate', self::NEPAL_VAT_RATE);
    }
}

