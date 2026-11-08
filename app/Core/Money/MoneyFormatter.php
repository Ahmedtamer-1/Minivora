<?php

namespace App\Core\Money;

/**
 * Formats piasters as EGP with Western digits in both languages. NFR-USE-05
 * Example: 805997 → "8,059.97 EGP" (en) / "8,059.97 ج.م" (ar).
 */
final class MoneyFormatter
{
    public static function format(Piasters|int $money, string $locale = 'en'): string
    {
        $amount = $money instanceof Piasters ? $money->amount : $money;
        $sign = $amount < 0 ? '-' : '';
        $abs = abs($amount);

        $number = number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $sign.$number.' '.($locale === 'ar' ? 'ج.م' : 'EGP');
    }
}
