<?php

namespace App\Modules\Seo\Services;

/**
 * Audits a storefront page for on-page SEO quality and computes a 0..100 score. FR-SEO-13, FR-SEO-19
 */
class SeoAuditService
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 70;

    public const DESCRIPTION_MAX = 155;

    public const MIN_WORD_COUNT = 150;

    /**
     * Audit page SEO attributes and return a score (0..100) and structured list of issues. FR-SEO-19
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     h1_count?: int|null,
     *     h1s?: list<string>,
     *     images?: list<array{src?: string|null, alt?: string|null}|string>,
     *     content?: string|null,
     *     word_count?: int|null,
     *     json_ld?: array<mixed>|bool|null
     * }  $pageData
     * @param  list<string>  $otherTitles
     * @param  list<string>  $otherDescriptions
     * @return array{score: int, issues: list<array{code: string, severity: string, message: string}>}
     */
    public function audit(array $pageData, array $otherTitles = [], array $otherDescriptions = []): array
    {
        $score = 100;
        $issues = [];

        $title = trim((string) ($pageData['title'] ?? ''));
        $titleLen = mb_strlen($title, 'UTF-8');

        if ($title === '') {
            $score -= 20;
            $issues[] = [
                'code' => 'missing_title',
                'severity' => 'error',
                'message' => 'Page is missing a meta title.',
            ];
        } elseif ($titleLen < self::TITLE_MIN || $titleLen > self::TITLE_MAX) {
            $score -= 10;
            $issues[] = [
                'code' => 'title_length',
                'severity' => 'warning',
                'message' => sprintf(
                    'Meta title length is %d characters; recommended range is %d–%d characters.',
                    $titleLen,
                    self::TITLE_MIN,
                    self::TITLE_MAX,
                ),
            ];
        }

        if ($title !== '' && $this->containsCaseInsensitive($title, $otherTitles)) {
            $score -= 15;
            $issues[] = [
                'code' => 'duplicate_title',
                'severity' => 'error',
                'message' => 'Meta title duplicates another page in this store.',
            ];
        }

        $description = trim((string) ($pageData['description'] ?? ''));
        $descLen = mb_strlen($description, 'UTF-8');

        if ($description === '') {
            $score -= 15;
            $issues[] = [
                'code' => 'missing_description',
                'severity' => 'error',
                'message' => 'Page is missing a meta description.',
            ];
        } elseif ($descLen < self::DESCRIPTION_MIN || $descLen > self::DESCRIPTION_MAX) {
            $score -= 10;
            $issues[] = [
                'code' => 'description_length',
                'severity' => 'warning',
                'message' => sprintf(
                    'Meta description length is %d characters; recommended range is %d–%d characters.',
                    $descLen,
                    self::DESCRIPTION_MIN,
                    self::DESCRIPTION_MAX,
                ),
            ];
        }

        if ($description !== '' && $this->containsCaseInsensitive($description, $otherDescriptions)) {
            $score -= 10;
            $issues[] = [
                'code' => 'duplicate_description',
                'severity' => 'warning',
                'message' => 'Meta description duplicates another page in this store.',
            ];
        }

        $h1Count = isset($pageData['h1_count'])
            ? (int) $pageData['h1_count']
            : (isset($pageData['h1s']) && is_array($pageData['h1s']) ? count($pageData['h1s']) : 0);

        if ($h1Count === 0) {
            $score -= 15;
            $issues[] = [
                'code' => 'missing_h1',
                'severity' => 'error',
                'message' => 'Page must contain exactly one H1 heading (found 0).',
            ];
        } elseif ($h1Count > 1) {
            $score -= 10;
            $issues[] = [
                'code' => 'multiple_h1',
                'severity' => 'warning',
                'message' => sprintf('Page must contain exactly one H1 heading (found %d).', $h1Count),
            ];
        }

        $missingAltCount = 0;
        foreach ($pageData['images'] ?? [] as $image) {
            if (is_array($image)) {
                $alt = trim((string) ($image['alt'] ?? ''));
                if ($alt === '') {
                    $missingAltCount++;
                }
            } else {
                $missingAltCount++;
            }
        }

        if ($missingAltCount > 0) {
            $score -= 10;
            $issues[] = [
                'code' => 'missing_image_alt',
                'severity' => 'warning',
                'message' => sprintf('%d image(s) are missing descriptive alt text.', $missingAltCount),
            ];
        }

        $wordCount = isset($pageData['word_count'])
            ? (int) $pageData['word_count']
            : $this->countWords((string) ($pageData['content'] ?? ''));

        if ($wordCount < self::MIN_WORD_COUNT) {
            $score -= 10;
            $issues[] = [
                'code' => 'thin_content',
                'severity' => 'warning',
                'message' => sprintf(
                    'Page content has %d words; recommended minimum is %d words.',
                    $wordCount,
                    self::MIN_WORD_COUNT,
                ),
            ];
        }

        $jsonLd = $pageData['json_ld'] ?? null;
        $hasJsonLd = is_bool($jsonLd) ? $jsonLd : (is_array($jsonLd) && $jsonLd !== []);

        if (! $hasJsonLd) {
            $score -= 10;
            $issues[] = [
                'code' => 'missing_json_ld',
                'severity' => 'warning',
                'message' => 'Page is missing JSON-LD structured data.',
            ];
        }

        return [
            'score' => max(0, min(100, $score)),
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<string>  $haystack
     */
    private function containsCaseInsensitive(string $needle, array $haystack): bool
    {
        $normalizedNeedle = mb_strtolower(trim($needle), 'UTF-8');

        foreach ($haystack as $candidate) {
            if (mb_strtolower(trim($candidate), 'UTF-8') === $normalizedNeedle) {
                return true;
            }
        }

        return false;
    }

    private function countWords(string $content): int
    {
        $plain = trim(strip_tags($content));

        if ($plain === '') {
            return 0;
        }

        $tokens = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? count($tokens) : 0;
    }
}
