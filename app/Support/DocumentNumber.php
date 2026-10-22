<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generates sequential document numbers per year, e.g. INV-2026-00001.
 * Must be called inside a transaction so the lock holds until the row is saved.
 */
class DocumentNumber
{
    public static function next(string $table, string $prefix): string
    {
        $base = $prefix.'-'.now()->format('Y').'-';

        $last = DB::table($table)
            ->where('number', 'like', $base.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
