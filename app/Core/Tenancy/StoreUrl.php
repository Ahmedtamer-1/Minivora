<?php

namespace App\Core\Tenancy;

/**
 * Builds absolute URLs for store subdomains and the platform host.
 */
final class StoreUrl
{
    public static function host(string $subdomain): string
    {
        return $subdomain.'.'.config('minivora.domain');
    }

    public static function base(string $subdomain): string
    {
        $port = (string) config('minivora.port');

        return config('minivora.scheme').'://'.self::host($subdomain).($port !== '' ? ':'.$port : '');
    }

    public static function to(string $subdomain, string $path = '/'): string
    {
        $path = ltrim($path, '/');

        return $path === '' ? self::base($subdomain) : self::base($subdomain).'/'.$path;
    }
}
