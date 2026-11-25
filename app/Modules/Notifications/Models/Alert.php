<?php

namespace App\Modules\Notifications\Models;

use App\Core\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * In-dashboard alert for store owners and staff (new orders, low stock, pending approvals, anomalies).
 * FR-NTF-04
 *
 * @property int $id
 * @property int $store_id
 * @property string $type
 * @property string $severity
 * @property string $title
 * @property string $body
 * @property string|null $action_url
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Alert extends Model
{
    use BelongsToStore;

    public const TYPES = ['new_order', 'low_stock', 'pending_approval', 'anomaly'];

    public const SEVERITIES = ['info', 'warning', 'critical'];

    protected $fillable = [
        'store_id',
        'type',
        'severity',
        'title',
        'body',
        'action_url',
        'meta',
        'read_at',
    ];

    protected $attributes = [
        'severity' => 'info',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => Carbon::now()])->save();
        }
    }
}
