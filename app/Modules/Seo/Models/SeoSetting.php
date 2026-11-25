<?php

namespace App\Modules\Seo\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-store SEO defaults, Open Graph image, verification tokens, and robots.txt rules. FR-SEO-02, FR-SEO-06, FR-SEO-08
 *
 * @property int $id
 * @property int $store_id
 * @property string|null $default_title_en
 * @property string|null $default_title_ar
 * @property string|null $default_description_en
 * @property string|null $default_description_ar
 * @property string|null $og_image
 * @property string|null $gsc_verification_token
 * @property string|null $robots_extra
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SeoSetting extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'default_title_en',
        'default_title_ar',
        'default_description_en',
        'default_description_ar',
        'og_image',
        'gsc_verification_token',
        'robots_extra',
    ];
}
