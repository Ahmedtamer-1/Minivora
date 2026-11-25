<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-store ad pixel, Conversions API, and read-only Meta ad account configuration.
 * FR-MKT-03, FR-MKT-04, FR-MI-02
 *
 * @property int $id
 * @property int $store_id
 * @property string|null $meta_pixel_id
 * @property string|null $meta_capi_token
 * @property string|null $meta_ad_account_id
 * @property string|null $tiktok_pixel_id
 * @property string|null $snap_pixel_id
 * @property string|null $ga4_measurement_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Pixel extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'meta_pixel_id',
        'meta_capi_token',
        'meta_ad_account_id',
        'tiktok_pixel_id',
        'snap_pixel_id',
        'ga4_measurement_id',
    ];

    protected $hidden = [
        'meta_capi_token',
    ];
}
