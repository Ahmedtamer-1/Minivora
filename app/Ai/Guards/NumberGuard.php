<?php

namespace App\Ai\Guards;

/**
 * Verifies that every numeric figure mentioned in an AI-generated answer matches a code-computed
 * value from the supplied facts array. FR-FPA-06, NFR-AIQ-01
 */
class NumberGuard
{
    /**
     * Returns true when every number found in $text appears in $facts (allowing comma formatting).
     *
     * @param  array<string, mixed>  $facts
     */
    public function verify(string $text, array $facts): bool
    {
        return $this->ungroundedNumbers($text, $facts) === [];
    }

    /**
     * List any numbers in $text that do not match a code-computed number in $facts.
     *
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    public function ungroundedNumbers(string $text, array $facts): array
    {
        $allowed = $this->extractFactNumbers($facts);

        // Strip ISO dates (YYYY-MM-DD) so calendar dates in period strings are not flagged as metrics.
        $cleaned = (string) preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', '', $text);

        preg_match_all('/(?<!\w)(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?(?!\w)/', $cleaned, $matches);

        $ungrounded = [];
        foreach ($matches[0] as $raw) {
            $normalized = $this->normalize($raw);
            if (! in_array($normalized, $allowed, true)) {
                $ungrounded[] = $raw;
            }
        }

        return $ungrounded;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return list<string>
     */
    private function extractFactNumbers(array $facts): array
    {
        $numbers = [];

        $walk = function (mixed $value) use (&$walk, &$numbers): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }

                return;
            }

            if (is_int($value) || is_float($value)) {
                $numbers[] = $this->normalize((string) $value);
                $numbers[] = $this->normalize(number_format((float) $value, 2, '.', ''));
                $numbers[] = $this->normalize((string) round((float) $value, 1));
            } elseif (is_string($value) && is_numeric(str_replace(',', '', $value))) {
                $numbers[] = $this->normalize($value);
            }
        };

        $walk($facts);

        return array_values(array_unique($numbers));
    }

    private function normalize(string $number): string
    {
        $clean = str_replace(',', '', trim($number));

        return (string) ((float) $clean);
    }
}
