<?php

namespace App\Modules\Platform\Services;

use App\Core\Tenancy\ReservedSubdomains;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Str;

/**
 * Checks store-address availability as the owner types and suggests alternatives. FR-ONB-02
 */
class SubdomainService
{
    public const OK = 'available';

    public const TAKEN = 'taken';

    public const RESERVED = 'reserved';

    public const INVALID = 'invalid';

    /**
     * Turn any store name (Arabic or English) into a candidate address: lowercase a-z, 0-9 and hyphens.
     */
    public function normalize(string $input): string
    {
        $slug = Str::slug(Str::ascii($input, 'ar'));
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim(substr($slug, 0, (int) config('minivora.subdomain.max')), '-');
    }

    public function isValidFormat(string $subdomain): bool
    {
        $min = (int) config('minivora.subdomain.min');
        $max = (int) config('minivora.subdomain.max');

        return strlen($subdomain) >= $min
            && strlen($subdomain) <= $max
            && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $subdomain) === 1
            && ! str_contains($subdomain, '--');
    }

    public function status(string $subdomain): string
    {
        return match (true) {
            ! $this->isValidFormat($subdomain) => self::INVALID,
            ReservedSubdomains::contains($subdomain) => self::RESERVED,
            Store::query()->where('subdomain', $subdomain)->exists() => self::TAKEN,
            default => self::OK,
        };
    }

    public function isAvailable(string $subdomain): bool
    {
        return $this->status($subdomain) === self::OK;
    }

    /**
     * Up to $limit available alternatives for a taken or reserved name.
     *
     * @return list<string>
     */
    public function suggest(string $subdomain, int $limit = 3): array
    {
        $base = $this->normalize($subdomain);

        if (strlen($base) < (int) config('minivora.subdomain.min')) {
            $base = $base === '' ? 'my-store' : $base.'-store';
        }

        $base = substr($base, 0, (int) config('minivora.subdomain.max') - 6);
        $year = now()->format('y');

        $candidates = [
            $base.'-eg', $base.'shop', $base.'-store', 'shop'.$base, $base.'-official', $base.$year,
            $base.'-'.random_int(10, 99), $base.'-'.random_int(100, 999),
        ];

        $taken = Store::query()->whereIn('subdomain', $candidates)->pluck('subdomain')->all();

        $suggestions = [];
        foreach ($candidates as $candidate) {
            if (count($suggestions) >= $limit) {
                break;
            }
            if ($this->isValidFormat($candidate) && ! ReservedSubdomains::contains($candidate) && ! in_array($candidate, $taken, true)) {
                $suggestions[] = $candidate;
            }
        }

        return $suggestions;
    }

    /**
     * Availability answer for the live check in the create-store form.
     *
     * @return array{subdomain: string, status: string, available: bool, suggestions: list<string>}
     */
    public function check(string $input): array
    {
        $subdomain = $this->normalize($input);
        $status = $this->status($subdomain);

        return [
            'subdomain' => $subdomain,
            'status' => $status,
            'available' => $status === self::OK,
            'suggestions' => $status === self::OK ? [] : $this->suggest($subdomain === '' ? $input : $subdomain),
        ];
    }
}
