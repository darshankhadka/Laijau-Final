<?php

declare(strict_types=1);

namespace App\Helpers;

class NepaliNumberHelper
{
    /**
     * Formats any integer or float according to the Nepali / South Asian numbering system.
     * Rule: The rightmost 3 digits are grouped together, and all preceding digits are grouped in pairs of 2.
     * Examples:
     *   100000   => 1,00,000
     *   1250000  => 12,50,000
     *   10000000 => 1,00,00,000
     *
     * @param mixed $number
     * @param int|null $decimals If null, decimals will be preserved if present (up to 2). If int, forced to that precision.
     * @return string
     */
    public static function format($number, ?int $decimals = null): string
    {
        if ($number === null || $number === '') {
            return '0';
        }

        $floatVal = (float) $number;
        $isNegative = $floatVal < 0;
        $absVal = abs($floatVal);

        if ($decimals === null) {
            $hasDecimals = floor($absVal) != $absVal;
            $decimals = $hasDecimals ? 2 : 0;
        }

        $formatted = number_format($absVal, $decimals, '.', '');
        $parts = explode('.', $formatted);
        $integerPart = $parts[0];
        $decimalPart = (isset($parts[1]) && $decimals > 0) ? '.' . $parts[1] : '';

        if (strlen($integerPart) <= 3) {
            $grouped = $integerPart;
        } else {
            $lastThree = substr($integerPart, -3);
            $remaining = substr($integerPart, 0, -3);

            $groups = [];
            while (strlen($remaining) > 2) {
                $groups[] = substr($remaining, -2);
                $remaining = substr($remaining, 0, -2);
            }
            if (strlen($remaining) > 0) {
                $groups[] = $remaining;
            }

            $grouped = implode(',', array_reverse($groups)) . ',' . $lastThree;
        }

        return ($isNegative ? '-' : '') . $grouped . $decimalPart;
    }

    /**
     * Formats an amount with currency prefix in Nepali numbering.
     * E.g. 100000 => "Rs. 1,00,000"
     *
     * @param mixed $amount
     * @param string $prefix
     * @param int|null $decimals
     * @return string
     */
    public static function formatCurrency($amount, string $prefix = 'Rs. ', ?int $decimals = null): string
    {
        $formatted = static::format($amount, $decimals);
        return $prefix . $formatted;
    }

    /**
     * Formats an amount into human-facing Nepali Lakh/Crore notation.
     * Rules per Nepal financial reporting standard:
     *   >= 1,00,00,000 (1 crore): X.XX crore (e.g. 36472322.78 => "Rs. 3.65 crore", 10000000 => "Rs. 1 crore")
     *   >= 1,00,000 (1 lakh): X.XX lakh (e.g. 500000 => "Rs. 5 lakh", 100000 => "Rs. 1 lakh")
     *   < 1,00,000: exact currency formatted (e.g. 1000 => "Rs. 1,000.00")
     *
     * @param mixed $amount
     * @param string $prefix
     * @param int $decimals
     * @return string
     */
    public static function formatLakhCrore($amount, string $prefix = 'Rs. ', int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return $prefix . '0';
        }

        $floatVal = (float) $amount;
        $isNegative = $floatVal < 0;
        $absVal = abs($floatVal);

        if ($absVal >= 10000000) {
            $val = round($absVal / 10000000, $decimals);
            $str = (floor($val) == $val) ? number_format($val, 0) : number_format($val, $decimals);
            return ($isNegative ? '-' : '') . $prefix . $str . ' crore';
        } elseif ($absVal >= 100000) {
            $val = round($absVal / 100000, $decimals);
            $str = (floor($val) == $val) ? number_format($val, 0) : number_format($val, $decimals);
            return ($isNegative ? '-' : '') . $prefix . $str . ' lakh';
        } else {
            return ($isNegative ? '-' : '') . static::formatCurrency($absVal, $prefix);
        }
    }
}


