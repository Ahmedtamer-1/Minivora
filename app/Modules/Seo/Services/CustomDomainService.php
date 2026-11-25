<?php

namespace App\Modules\Seo\Services;

/**
 * Canonical host normalization and HTTPS / www <-> non-www redirect resolution
 * for custom storefront domains. FR-SEO-21
 */
class CustomDomainService
{
    /**
     * Normalize a domain to its canonical host (with or without leading "www.").
     */
    public function canonicalHost(string $domain, bool $preferWww = false): string
    {
        $normalized = $this->normalizeHost($domain);
        $bare = preg_replace('/^www\./i', '', $normalized) ?? $normalized;

        return $preferWww ? 'www.'.$bare : $bare;
    }

    /**
     * Return the canonical HTTPS redirect URL when scheme is not HTTPS or host differs
     * from the canonical host (e.g. www. <-> non-www.), or null if already canonical HTTPS.
     */
    public function redirectUrlFor(string $scheme, string $host, string $path, string $canonicalHost): ?string
    {
        $normalizedScheme = strtolower(trim($scheme));
        $normalizedHost = $this->normalizeHost($host);
        $normalizedCanonical = $this->normalizeHost($canonicalHost);
        $normalizedPath = $path === '' ? '/' : (str_starts_with($path, '/') ? $path : '/'.$path);

        if ($normalizedScheme === 'https' && $normalizedHost === $normalizedCanonical) {
            return null;
        }

        return "https://{$normalizedCanonical}{$normalizedPath}";
    }

    private function normalizeHost(string $host): string
    {
        $cleaned = strtolower(trim($host));
        $cleaned = preg_replace('#^https?://#i', '', $cleaned) ?? $cleaned;
        $cleaned = explode('/', $cleaned, 2)[0];
        $cleaned = explode(':', $cleaned, 2)[0];

        return rtrim($cleaned, '.');
    }
}
