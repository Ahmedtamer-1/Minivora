<?php

namespace App\Modules\Seo\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * URL redirect rule (301/302/307/308) scoped to a store. FR-SEO-12, FR-SEO-23
 *
 * @property int $id
 * @property int $store_id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property bool $is_automatic
 * @property int $hits
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Redirect extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
        'is_automatic',
        'hits',
    ];

    protected $attributes = [
        'status_code' => 301,
        'is_automatic' => false,
        'hits' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_automatic' => 'boolean',
            'hits' => 'integer',
        ];
    }
}
