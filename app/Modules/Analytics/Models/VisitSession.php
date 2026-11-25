<?php

namespace App\Modules\Analytics\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * First-party shopper visit session with UTM attribution and device type. FR-ANA-01, FR-ANA-02
 *
 * @property int $id
 * @property int $store_id
 * @property string $session_key
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer
 * @property string $device_type
 * @property Carbon $started_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VisitSession extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'session_key',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer',
        'device_type',
        'started_at',
    ];

    protected $attributes = [
        'device_type' => 'mobile',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
        ];
    }

    /** @return HasMany<PageView, $this> */
    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }
}
