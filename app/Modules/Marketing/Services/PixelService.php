<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Marketing\Models\Pixel;

/**
 * Manage per-store tracking pixel IDs and CAPI credentials. FR-MKT-03
 */
class PixelService
{
    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'meta_pixel_id',
        'meta_capi_token',
        'meta_ad_account_id',
        'tiktok_pixel_id',
        'snap_pixel_id',
        'ga4_measurement_id',
    ];

    public function current(): Pixel
    {
        return Pixel::query()->firstOrCreate([]);
    }

    /** @param  array<string, mixed>  $ids */
    public function update(array $ids): Pixel
    {
        $pixel = $this->current();

        foreach (self::ALLOWED_KEYS as $key) {
            if (! array_key_exists($key, $ids)) {
                continue;
            }

            $value = $ids[$key];
            if (is_string($value)) {
                $value = trim($value);
                $pixel->setAttribute($key, $value === '' ? null : $value);
            } elseif ($value === null) {
                $pixel->setAttribute($key, null);
            }
        }

        $pixel->save();

        return $pixel->refresh();
    }
}
