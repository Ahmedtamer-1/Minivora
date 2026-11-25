<?php

namespace App\Modules\Checkout\Services;

use Illuminate\Validation\ValidationException;

/**
 * Egyptian mobile phone normalizer and validator. FR-PAY-02
 *
 * Accepts 01XXXXXXXXX, +201XXXXXXXXX, or 00201XXXXXXXXX (ignoring spaces and dashes),
 * validates 11 digits with prefix 010, 011, 012, or 015, and returns normalized 01XXXXXXXXX.
 */
final class EgyptianPhone
{
    private const ARABIC_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * @throws ValidationException
     */
    public static function normalize(string $raw): string
    {
        $clean = strtr(trim($raw), self::ARABIC_DIGITS);
        $clean = preg_replace('/[\s\-]+/', '', $clean) ?? '';

        if (str_starts_with($clean, '+20')) {
            $clean = '0'.substr($clean, 3);
        } elseif (str_starts_with($clean, '0020')) {
            $clean = '0'.substr($clean, 4);
        }

        if (! preg_match('/^01[0125]\d{8}$/', $clean)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Egyptian mobile number (010, 011, 012, or 015).',
            ]);
        }

        return $clean;
    }

    public static function isValid(string $raw): bool
    {
        try {
            self::normalize($raw);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
