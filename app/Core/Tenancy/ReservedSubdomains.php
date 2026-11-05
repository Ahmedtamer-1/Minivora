<?php

namespace App\Core\Tenancy;

/**
 * Names that can never be store names: app, api, www, mail, admin, status (and a few more). FR-ONB-02
 */
final class ReservedSubdomains
{
    /** @return list<string> */
    public static function all(): array
    {
        return array_values(config('minivora.reserved_subdomains', []));
    }

    public static function contains(string $name): bool
    {
        return in_array(strtolower($name), self::all(), true);
    }
}
