<?php

namespace App\Modules\Ledger\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A calendar date stored as "Y-m-d" on every database. Laravel's own "date" cast writes a time
 * part on SQLite, which breaks range queries such as entry_date <= '2026-10-31'.
 *
 * @implements CastsAttributes<Carbon, mixed>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse(substr((string) $value, 0, 10))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value))->toDateString();
    }
}
