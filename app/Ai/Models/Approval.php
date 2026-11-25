<?php

namespace App\Ai\Models;

use App\Core\Tenancy\BelongsToStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pending or decided AI act-level proposal. FR-AI-07, FR-AI-10
 *
 * @property int $id
 * @property int $store_id
 * @property string $agent
 * @property string $function_name
 * @property array<string, mixed> $payload
 * @property int $estimated_impact
 * @property string $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property array<string, mixed>|null $result
 */
class Approval extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'store_id',
        'agent',
        'function_name',
        'payload',
        'estimated_impact',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'result',
    ];

    protected $attributes = [
        'estimated_impact' => 0,
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'estimated_impact' => 'integer',
            'decided_at' => 'datetime',
            'result' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
