<?php

namespace App\Modules\Analytics\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * First-party storefront page view event. FR-ANA-01, FR-ANA-04, FR-ANA-06
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $visit_session_id
 * @property string $path
 * @property int|null $product_id
 * @property string|null $referrer
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string $device_type
 * @property Carbon $viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PageView extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'visit_session_id',
        'path',
        'product_id',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'viewed_at',
    ];

    protected $attributes = [
        'device_type' => 'mobile',
    ];

    protected function casts(): array
    {
        return [
            'visit_session_id' => 'integer',
            'product_id' => 'integer',
            'viewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VisitSession, $this> */
    public function visitSession(): BelongsTo
    {
        return $this->belongsTo(VisitSession::class);
    }
}
