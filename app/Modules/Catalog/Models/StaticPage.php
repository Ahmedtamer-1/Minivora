<?php

namespace App\Modules\Catalog\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store static informational page (about, contact, privacy, terms, shipping, exchange, returns). FR-CAT-11
 *
 * @property int $id
 * @property int $store_id
 * @property string $slug
 * @property string $title
 * @property string|null $title_ar
 * @property string|null $body
 * @property string|null $body_ar
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class StaticPage extends Model
{
    use BelongsToStore;

    public const ALLOWED_SLUGS = [
        'about',
        'contact',
        'privacy',
        'terms',
        'shipping',
        'exchange',
        'returns',
    ];

    protected $fillable = [
        'slug',
        'title',
        'title_ar',
        'body',
        'body_ar',
    ];
}
