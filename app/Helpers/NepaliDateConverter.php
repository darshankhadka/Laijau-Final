<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use InvalidArgumentException;

class NepaliDateConverter
{
    /**
     * Days per Bikram Sambat month for relevant years.
     * Index 1 = Baisakh, 12 = Chaitra.
     */
    private static array $bsMonthDays = [
        2081 => [1 => 31, 2 => 32, 3 => 31, 4 => 32, 5 => 31, 6 => 30, 7 => 30, 8 => 30, 9 => 29, 10 => 30, 11 => 29, 12 => 31],
        2082 => [1 => 31, 2 => 31, 3 => 32, 4 => 31, 5 => 31, 6 => 31, 7 => 30, 8 => 29, 9 => 30, 10 => 29, 11 => 30, 12 => 30],
        2083 => [1 => 31, 2 => 31, 3 => 32, 4 => 31, 5 => 31, 6 => 31, 7 => 30, 8 => 29, 9 => 30, 10 => 29, 11 => 30, 12 => 30],
        2084 => [1 => 31, 2 => 31, 3 => 31, 4 => 32, 5 => 31, 6 => 31, 7 => 30, 8 => 29, 9 => 30, 10 => 29, 11 => 30, 12 => 30],
    ];

    /**
     * Gregorian start date (Baisakh 1) for each Bikram Sambat year.
     */
    private static array $bsStartInAd = [
        2081 => '2024-04-13',
        2082 => '2025-04-13',
        2083 => '2026-04-13',
        2084 => '2027-04-14',
    ];

    /**
     * Convert Bikram Sambat (BS) date to Gregorian (AD) string 'YYYY-MM-DD'.
     */
    public static function bsToAd(int $bsYear, int $bsMonth, int $bsDay): string
    {
        if (!isset(self::$bsStartInAd[$bsYear])) {
            throw new InvalidArgumentException("Unsupported BS year: {$bsYear}");
        }

        if ($bsMonth < 1 || $bsMonth > 12) {
            throw new InvalidArgumentException("Invalid BS month: {$bsMonth}");
        }

        $maxDays = self::$bsMonthDays[$bsYear][$bsMonth] ?? 30;
        if ($bsDay < 1 || $bsDay > $maxDays) {
            $bsDay = min(max(1, $bsDay), $maxDays);
        }

        // Count elapsed days from Baisakh 1 of $bsYear
        $daysPassed = 0;
        for ($m = 1; $m < $bsMonth; $m++) {
            $daysPassed += self::$bsMonthDays[$bsYear][$m];
        }
        $daysPassed += ($bsDay - 1);

        $startDate = new DateTimeImmutable(self::$bsStartInAd[$bsYear]);
        $targetDate = $startDate->modify("+{$daysPassed} days");

        return $targetDate->format('Y-m-d');
    }

    /**
     * Alias for parseBsToAd.
     */
    public static function convertDateString(string $dateStr): ?string
    {
        return self::parseBsToAd($dateStr);
    }

    /**
     * Parse any flexible Nepali BS date string into 'YYYY-MM-DD' (AD).
     * Handles formats:
     * - 'YYYY/MM/DD' or 'YYYY-MM-DD' (e.g. 2083/05/01, 2082-12-23)
     * - 'M/D/YYYY' or 'MM/DD/YYYY' (e.g. 9/19/2082, 10/1/2082, 1/4/2083)
     * - Fallback for already Gregorian dates (2025/2026) -> returns as is.
     */
    public static function parseBsToAd(string $dateStr): ?string
    {
        $trimmed = trim($dateStr);
        if (empty($trimmed)) {
            return null;
        }

        // If already AD date (starts with 2025 or 2026)
        if (preg_match('/^(202[4-7])[-|\/](\d{1,2})[-|\/](\d{1,2})/', $trimmed, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        // Format: YYYY/MM/DD or YYYY-MM-DD (e.g. 2082/10/18, 2083-05-01)
        if (preg_match('/^(208[1-4])[-|\/](\d{1,2})[-|\/](\d{1,2})/', $trimmed, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
            return self::bsToAd($year, $month, $day);
        }

        // Format: M/D/YYYY or MM/DD/YYYY (e.g. 9/19/2082, 10/28/2082, 1/15/2083)
        if (preg_match('/^(\d{1,2})[-|\/](\d{1,2})[-|\/](208[1-4])/', $trimmed, $m)) {
            $month = (int)$m[1];
            $day = (int)$m[2];
            $year = (int)$m[3];
            return self::bsToAd($year, $month, $day);
        }

        return null;
    }
}
