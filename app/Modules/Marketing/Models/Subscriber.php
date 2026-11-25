<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store newsletter subscriber. FR-MKT-05
 *
 * @property int $id
 * @property int $store_id
 * @property string $email
 * @property string|null $name
 * @property string $source
 * @property Carbon $subscribed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Subscriber extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'email',
        'name',
        'source',
        'subscribed_at',
    ];

    protected $attributes = [
        'source' => 'storefront',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
        ];
    }
}
