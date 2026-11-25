<?php

namespace App\Importers\Adorna;

use InvalidArgumentException;

/**
 * Exact half-up conversion from Adorna EG pound amounts (EGP) into integer piasters.
 * Never stores floats; all money values in Minivora are BIGINT piasters (1 EGP = 100 piasters).
 */
final class AdornaMoney
{
    /**
     * Convert an EGP amount (int, float, or numeric string like "125.50" or "125.505")
     * into integer piasters using half-up rounding.
     */
    public static function fromEgp(int|float|string $amount): int
    {
        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            $raw = rtrim(rtrim(sprintf('%.10F', $amount), '0'), '.');
        } else {
            $raw = str_replace([',', ' '], '', trim($amount));
        }

        if ($raw === '' || ! preg_match('/^(-)?(\d+)(?:\.(\d+))?$/', $raw, $matches)) {
            throw new InvalidArgumentException("Invalid EGP amount for Adorna import: {$amount}");
        }

        $negative = $matches[1] === '-';
        $whole = (int) $matches[2];
        $fraction = $matches[3] ?? '';

        $firstTwo = (int) str_pad(substr($fraction, 0, 2), 2, '0');
        $thirdDigit = isset($fraction[2]) ? (int) $fraction[2] : 0;

        $piasters = ($whole * 100) + $firstTwo + ($thirdDigit >= 5 ? 1 : 0);

        return $negative ? -$piasters : $piasters;
    }

    /**
     * Extract a money field from an Adorna row as integer piasters.
     * Supports explicit `{$field}_piasters` (already integer piasters), `{$field}_egp`,
     * or `{$field}` (interpreted as EGP pounds, rounded half-up to piasters).
     *
     * @param  array<string, mixed>  $row
     */
    public static function extract(array $row, string $field, ?int $default = 0): ?int
    {
        $piastersKey = "{$field}_piasters";
        if (array_key_exists($piastersKey, $row) && $row[$piastersKey] !== null && $row[$piastersKey] !== '') {
            return (int) $row[$piastersKey];
        }

        $egpKey = "{$field}_egp";
        if (array_key_exists($egpKey, $row) && $row[$egpKey] !== null && $row[$egpKey] !== '') {
            return self::fromEgp($row[$egpKey]);
        }

        if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
            return self::fromEgp($row[$field]);
        }

        return $default;
    }
}
