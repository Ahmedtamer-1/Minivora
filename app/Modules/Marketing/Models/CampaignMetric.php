<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily imported campaign performance metrics. FR-MI-03, FR-MI-04
 * Money columns (spend, meta_purchase_value) hold integer piasters.
 *
 * @property int $id
 * @property int $store_id
 * @property int $campaign_id
 * @property Carbon $metric_date
 * @property int $spend
 * @property int $impressions
 * @property int $reach
 * @property int $clicks
 * @property float $frequency
 * @property int $meta_purchases
 * @property int $meta_purchase_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CampaignMetric extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'campaign_id',
        'metric_date',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'frequency',
        'meta_purchases',
        'meta_purchase_value',
    ];

    protected $attributes = [
        'spend' => 0,
        'impressions' => 0,
        'reach' => 0,
        'clicks' => 0,
        'frequency' => 0,
        'meta_purchases' => 0,
        'meta_purchase_value' => 0,
    ];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'metric_date' => 'date',
            'spend' => 'integer',
            'impressions' => 'integer',
            'reach' => 'integer',
            'clicks' => 'integer',
            'frequency' => 'float',
            'meta_purchases' => 'integer',
            'meta_purchase_value' => 'integer',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
