<?php

namespace App\Modules\Marketing\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Imported ad campaign or AI-generated campaign proposal awaiting approval.
 * FR-MI-03, FR-MI-04, FR-MI-07, FR-MI-08
 *
 * @property int $id
 * @property int $store_id
 * @property string|null $external_id
 * @property string $channel
 * @property string $name
 * @property string|null $utm_campaign
 * @property string $status
 * @property bool $is_proposal
 * @property array<string, mixed>|null $proposal_payload
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Campaign extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'external_id',
        'channel',
        'name',
        'utm_campaign',
        'status',
        'is_proposal',
        'proposal_payload',
        'approved_at',
    ];

    protected $attributes = [
        'channel' => 'meta',
        'status' => 'active',
        'is_proposal' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_proposal' => 'boolean',
            'proposal_payload' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return HasMany<CampaignMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(CampaignMetric::class)->orderBy('metric_date');
    }
}
