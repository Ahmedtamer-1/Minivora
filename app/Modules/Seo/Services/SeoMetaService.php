<?php

namespace App\Modules\Seo\Services;

/**
 * Generates Arabic & English slugs, semantic URLs, canonical URLs, hreflang links,
 * and HTML/Open Graph/Twitter meta tags. FR-SEO-01, FR-SEO-02, FR-SEO-03, FR-SEO-04, FR-SEO-06, FR-SEO-08
 */
class SeoMetaService
{
    public const MAX_TITLE_LENGTH = 60;

    public const MAX_DESCRIPTION_LENGTH = 155;

    /** @var list<string> */
    private const PRIVATE_PATH_PREFIXES = [
        '/cart',
        '/checkout',
        '/account',
        '/search',
        '/admin',
        '/dashboard',
    ];

    /** @var list<string> */
    private const STRIPPED_QUERY_PARAMS = [
        'filter',
        'sort',
        'fbclid',
        'gclid',
        'msclkid',
        'mc_cid',
        'mc_eid',
        'ref',
    ];

    /**
     * Produce a readable, URL-safe slug that preserves Arabic letters and Latin alphanumerics. FR-SEO-01
     */
    public function slugify(string $text): string
    {
        // Strip Arabic diacritics (tashkeel) and tatweel elongation character.
        $cleaned = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', trim($text)) ?? '';
        $cleaned = mb_strtolower($cleaned, 'UTF-8');

        // Replace anything other than Arabic letters, Latin letters, and digits with a hyphen.
        $slug = preg_replace('/[^\p{Arabic}\p{Latin}\p{N}]+/u', '-', $cleaned) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    /**
     * Build a localized semantic URL: /{locale}/{type}/{slug}. FR-SEO-01
     */
    public function urlFor(string $baseUrl, string $locale, string $type, string $slug): string
    {
        $base = rtrim($baseUrl, '/');
        $locale = trim($locale, '/');
        $type = trim($type, '/');
        $slug = ltrim($slug, '/');

        return "{$base}/{$locale}/{$type}/{$slug}";
    }

    /**
     * Strip filter, sort, UTM, fbclid, gclid, and fragment parameters to yield a clean canonical URL. FR-SEO-03
     */
    public function canonicalUrl(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            return trim($url);
        }

        $result = '';

        if (isset($parts['scheme'], $parts['host'])) {
            $result .= $parts['scheme'].'://'.$parts['host'];

            if (isset($parts['port'])) {
                $result .= ':'.$parts['port'];
            }
        }

        $path = $parts['path'] ?? '/';
        $result .= $path !== '' ? $path : '/';

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $params);

            $kept = [];
            foreach ($params as $key => $value) {
                $lower = strtolower((string) $key);

                if (str_starts_with($lower, 'utm_')) {
                    continue;
                }

                if (in_array($lower, self::STRIPPED_QUERY_PARAMS, true)) {
                    continue;
                }

                if (str_starts_with($lower, 'filter_')) {
                    continue;
                }

                $kept[$key] = $value;
            }

            if ($kept !== []) {
                $result .= '?'.http_build_query($kept);
            }
        }

        return $result;
    }

    /**
     * Build alternate hreflang links for Arabic, English, and x-default. FR-SEO-04
     *
     * @return array{ar: string, en: string, 'x-default': string}
     */
    public function hreflangLinks(string $baseUrl, string $type, string $slugAr, string $slugEn): array
    {
        $arUrl = $this->urlFor($baseUrl, 'ar', $type, $slugAr);
        $enUrl = $this->urlFor($baseUrl, 'en', $type, $slugEn);

        return [
            'ar' => $arUrl,
            'en' => $enUrl,
            'x-default' => $arUrl,
        ];
    }

    /**
     * Build normalized SEO, Open Graph, Twitter Card, and robots meta tags. FR-SEO-02, FR-SEO-06, FR-SEO-08
     *
     * @param  array{
     *     title?: string|null,
     *     description?: string|null,
     *     url?: string|null,
     *     canonical?: string|null,
     *     image?: string|null,
     *     hreflang?: array<string, string>,
     *     is_private?: bool,
     *     robots?: string|null,
     *     og_type?: string|null,
     *     path?: string|null
     * }  $input
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     hreflang: array<string, string>,
     *     robots: string,
     *     og: array{
     *         'og:title': string,
     *         'og:description': string,
     *         'og:image': ?string,
     *         'og:url': string,
     *         'og:type': string
     *     },
     *     twitter: array{
     *         'twitter:card': string,
     *         'twitter:title': string,
     *         'twitter:description': string,
     *         'twitter:image': ?string
     *     }
     * }
     */
    public function metaTags(array $input): array
    {
        $title = $this->truncate((string) ($input['title'] ?? ''), self::MAX_TITLE_LENGTH);
        $description = $this->truncate((string) ($input['description'] ?? ''), self::MAX_DESCRIPTION_LENGTH);

        $rawUrl = (string) ($input['canonical'] ?? $input['url'] ?? '');
        $canonical = $rawUrl !== '' ? $this->canonicalUrl($rawUrl) : '';
        $image = isset($input['image']) && trim((string) $input['image']) !== ''
            ? trim((string) $input['image'])
            : null;

        $path = $input['path'] ?? (string) (parse_url($canonical, PHP_URL_PATH) ?? '');
        $isPrivate = (bool) ($input['is_private'] ?? false) || $this->isPrivatePath($path);
        $robots = $isPrivate
            ? 'noindex, nofollow'
            : (string) ($input['robots'] ?? 'index, follow');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'hreflang' => $input['hreflang'] ?? [],
            'robots' => $robots,
            'og' => [
                'og:title' => $title,
                'og:description' => $description,
                'og:image' => $image,
                'og:url' => $canonical,
                'og:type' => (string) ($input['og_type'] ?? 'website'),
            ],
            'twitter' => [
                'twitter:card' => $image !== null ? 'summary_large_image' : 'summary',
                'twitter:title' => $title,
                'twitter:description' => $description,
                'twitter:image' => $image,
            ],
        ];
    }

    private function isPrivatePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $normalized = '/'.ltrim($path, '/');
        // Strip leading /ar or /en locale prefix if present.
        $withoutLocale = preg_replace('#^/(ar|en)(?=/|$)#', '', $normalized) ?? $normalized;
        $withoutLocale = $withoutLocale === '' ? '/' : $withoutLocale;

        foreach (self::PRIVATE_PATH_PREFIXES as $prefix) {
            if ($withoutLocale === $prefix || str_starts_with($withoutLocale, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $text, int $maxLength): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($clean, 'UTF-8') <= $maxLength) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, $maxLength, 'UTF-8'));
    }
}
