<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    /**
     * Atomically allocate the next sequential document number for a given scope.
     *
     * @param string $scope Unique scope key (e.g. 'journal_entry_2026', 'offline_sale')
     * @param string $prefix Formatted prefix (e.g. 'BIL-2026-', 'OFF-')
     * @param int $padding Number of zero-padded digits (e.g. 4 for 0001, 6 for 000001)
     * @param callable|null $seedResolver Optional callback returning the current maximum existing sequence number
     * @return string The allocated formatted sequence string (e.g. 'BIL-2026-0001')
     */
    public function next(string $scope, string $prefix, int $padding = 4, ?callable $seedResolver = null): string
    {
        return DB::transaction(function () use ($scope, $prefix, $padding, $seedResolver) {
            $row = DB::table('document_sequences')
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                // Initialize sequence from existing table max or 1
                $initialSeq = 1;
                if ($seedResolver !== null) {
                    try {
                        $maxExisting = (int)$seedResolver($prefix);
                        $initialSeq = max(1, $maxExisting + 1);
                    } catch (\Throwable $e) {
                        $initialSeq = 1;
                    }
                }

                DB::table('document_sequences')->insert([
                    'scope' => $scope,
                    'prefix' => $prefix,
                    'next_number' => $initialSeq + 1,
                    'padding' => $padding,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return sprintf('%s%0' . $padding . 'd', $prefix, $initialSeq);
            }

            $currentNumber = (int)$row->next_number;

            DB::table('document_sequences')
                ->where('scope', $scope)
                ->update([
                    'prefix' => $prefix,
                    'next_number' => $currentNumber + 1,
                    'padding' => $padding,
                    'updated_at' => now(),
                ]);

            return sprintf('%s%0' . $padding . 'd', $prefix, $currentNumber);
        });
    }

    /**
     * Helper to extract the highest integer suffix from a table column matching a prefix.
     */
    public static function determineMaxFromTable(string $table, string $column, string $prefix): int
    {
        $escaped = preg_quote($prefix, '/');
        $latest = DB::table($table)
            ->where($column, 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value($column);

        if ($latest && preg_match('/^' . $escaped . '(\d+)$/', (string)$latest, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}
