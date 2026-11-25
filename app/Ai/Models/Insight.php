<?php

namespace App\Ai\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proactive AI insight card produced by hourly, daily, weekly, or event-triggered checks.
 * FR-PRO-01..06
 *
 * @property int $id
 * @property int $store_id
 * @property string $agent
 * @property string $cadence
 * @property string $type
 * @property int $urgency
 * @property int $estimated_impact
 * @property float $score
 * @property string $title
 * @property string $summary
 * @property array<string, mixed> $evidence
 * @property array<string, mixed>|null $recommended_action
 * @property string $status
 * @property Carbon|null $snoozed_until
 * @property bool $is_urgent
 */
class Insight extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'agent',
        'cadence',
        'type',
        'urgency',
        'estimated_impact',
        'score',
        'title',
        'summary',
        'evidence',
        'recommended_action',
        'status',
        'snoozed_until',
        'is_urgent',
    ];

    protected $attributes = [
        'cadence' => 'daily',
        'urgency' => 50,
        'estimated_impact' => 0,
        'score' => 0,
        'status' => 'open',
        'is_urgent' => false,
    ];

    protected function casts(): array
    {
        return [
            'urgency' => 'integer',
            'estimated_impact' => 'integer',
            'score' => 'float',
            'evidence' => 'array',
            'recommended_action' => 'array',
            'snoozed_until' => 'datetime',
            'is_urgent' => 'boolean',
        ];
    }
}
