<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free, race-safe counters (invoice numbers and the like).
 */
final class Sequence
{
    public static function next(string $name): int
    {
        return DB::transaction(function () use ($name): int {
            $row = DB::table('sequences')->where('name', $name)->lockForUpdate()->first();
            $value = ($row === null ? 0 : (int) $row->value) + 1;

            DB::table('sequences')->updateOrInsert(['name' => $name], ['value' => $value]);

            return $value;
        });
    }
}
