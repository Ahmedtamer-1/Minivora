<?php

namespace App\Core\Tenancy;

/**
 * Builds absolute URLs for stores and the platform host.
 *
 * Two modes (config minivora.storefront_mode / STOREFRONT_MODE):
 * - "subdomain": <store>.<domain> — needs wildcard DNS and a wildcard TLS certificate.
 * - "path": <app url>/s/<store> — works on any single host (shared hosting, free hosting
 *   subdomains), at the cost of sharing one origin with the dashboard.
 */
final class StoreUrl
{
    public static function pathMode(): bool
    {
        return config('minivora.storefront_mode') === 'path';
    }

    /** Path prefix that comes before every storefront path: "" (subdomain mode) or "/s/<store>". */
    public static function prefix(string $subdomain): string
    {
        return self::pathMode() ? '/s/'.$subdomain : '';
    }

    public static function host(string $subdomain): string
    {
        if (self::pathMode()) {
            return (string) parse_url((string) config('app.url'), PHP_URL_HOST).'/s/'.$subdomain;
        }

        return $subdomain.'.'.config('minivora.domain');
    }

    public static function base(string $subdomain): string
    {
        if (self::pathMode()) {
            return rtrim((string) config('app.url'), '/').'/s/'.$subdomain;
        }

        $port = (string) config('minivora.port');

        return config('minivora.scheme').'://'.self::host($subdomain).($port !== '' ? ':'.$port : '');
    }

    public static function to(string $subdomain, string $path = '/'): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? self::base($subdomain) : self::base($subdomain).'/'.$path;
    }
}
