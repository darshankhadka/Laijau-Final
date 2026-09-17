<?php

namespace App\Services;

use App\Models\Setting;

class CurrencyService
{
    /**
     * Get list of all supported currencies with formatting metadata.
     * Strictly Nepalese Rupee (NPR) only.
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'NPR' => [
                'iso_code' => 'NPR',
                'symbol' => 'Rs. ',
                'symbol_position' => 'before',
                'name' => 'Nepalese Rupee',
                'precision' => 2,
                'exchange_rate' => 1.0, // Base currency
                'is_default' => true,
                'is_enabled' => true,
            ],
        ];
    }

    /**
     * Get authoritative default currency.
     */
    public function getDefaultCurrency(): string
    {
        return 'NPR';
    }

    /**
     * Check if currency code is supported (strictly NPR).
     */
    public function isValidCurrency(string $currency): bool
    {
        return strtoupper(trim($currency)) === 'NPR';
    }

    /**
     * Authoritatively convert amount between currencies (strictly NPR).
     */
    public function convert(float $amount, string $from = 'NPR', string $to = 'NPR'): float
    {
        return round($amount, 2);
    }

    /**
     * Format monetary value nicely with appropriate symbol placement.
     */
    public function format(float $amount, string $currency = 'NPR'): string
    {
        $formattedNumber = number_format($amount, 2, '.', ',');
        return 'Rs. ' . $formattedNumber;
    }
}

