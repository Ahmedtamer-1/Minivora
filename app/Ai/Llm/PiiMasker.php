<?php

namespace App\Ai\Llm;

/**
 * Strips customer and merchant PII before any prompt is sent to an external LLM provider.
 * NFR-PRV-01, V-17
 */
class PiiMasker
{
    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    // Egyptian mobile numbers: 01[0125]XXXXXXXX, +201[0125]XXXXXXXX, 00201[0125]XXXXXXXX (optional spaces/hyphens)
    private const EGYPT_PHONE_PATTERN = '/(?<!\d)(?:\+20|0020|0)[\s\-]?1[0125](?:[\s\-]?\d){8}(?!\d)/';

    // 14-digit Egyptian national IDs (century 2 or 3 + YY + MM + DD + 7 digits, or 2XXXXXXXXXXXXX)
    private const EGYPT_NATIONAL_ID_PATTERN = '/(?<!\d)(?:2\d{13}|3\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])\d{7})(?!\d)/';

    // Candidate payment card sequences (13 to 19 digits, optionally grouped by spaces or hyphens)
    private const CARD_CANDIDATE_PATTERN = '/(?<!\d)\d(?:[\s\-]?\d){12,18}(?!\d)/';

    public function mask(string $text): string
    {
        // 1. Mask emails first so digits inside local-parts are not partially matched.
        $masked = (string) preg_replace(self::EMAIL_PATTERN, '[EMAIL]', $text);

        // 2. Mask Egyptian mobile numbers.
        $masked = (string) preg_replace(self::EGYPT_PHONE_PATTERN, '[PHONE]', $masked);

        // 3. Mask 14-digit Egyptian national IDs before 13-19 digit card numbers.
        $masked = (string) preg_replace(self::EGYPT_NATIONAL_ID_PATTERN, '[NATIONAL_ID]', $masked);

        // 4. Mask 13-19 digit card numbers.
        $masked = (string) preg_replace_callback(
            self::CARD_CANDIDATE_PATTERN,
            static function (array $matches): string {
                $digits = preg_replace('/\D+/', '', $matches[0]) ?? '';
                $length = strlen($digits);

                return ($length >= 13 && $length <= 19) ? '[CARD]' : $matches[0];
            },
            $masked,
        );

        return $masked;
    }

    public function containsPii(string $text): bool
    {
        return $this->mask($text) !== $text;
    }
}
