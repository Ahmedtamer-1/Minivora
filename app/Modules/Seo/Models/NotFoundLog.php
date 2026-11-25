<?php

namespace App\Modules\Seo\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 404 Not Found hit log per store for identifying broken URLs. FR-SEO-24
 *
 * @property int $id
 * @property int $store_id
 * @property string $path
 * @property string|null $referrer
 * @property Carbon $hit_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotFoundLog extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'path',
        'referrer',
        'hit_at',
    ];

    protected function casts(): array
    {
        return [
            'hit_at' => 'datetime',
        ];
    }
}
