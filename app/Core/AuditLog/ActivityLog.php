<?php

namespace App\Core\AuditLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Activity log row. Append-only: each row stores the hash of the previous row, so editing or
 * deleting any row breaks the chain (tamper-evident). NFR-SEC-10, FR-ACC-05
 *
 * @property int $id
 * @property int|null $store_id
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip
 * @property string $prev_hash
 * @property string $hash
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The activity log is append-only.'));
        static::deleting(fn () => throw new LogicException('The activity log is append-only.'));
    }

    /**
     * The hash of a row's content chained to the previous hash. Field order is fixed.
     */
    public static function computeHash(string $prevHash, array $fields): string
    {
        return hash('sha256', $prevHash.'|'.json_encode([
            $fields['store_id'] ?? null,
            $fields['actor_type'] ?? null,
            $fields['actor_id'] ?? null,
            $fields['actor_name'] ?? null,
            $fields['action'] ?? null,
            $fields['subject_type'] ?? null,
            $fields['subject_id'] ?? null,
            $fields['properties'] ?? null,
            $fields['ip'] ?? null,
            $fields['created_at'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Raw column values in the form they were hashed. */
    public function hashableFields(): array
    {
        $properties = $this->getRawOriginal('properties');

        return [
            'store_id' => $this->getRawOriginal('store_id') === null ? null : (int) $this->getRawOriginal('store_id'),
            'actor_type' => $this->getRawOriginal('actor_type'),
            'actor_id' => $this->getRawOriginal('actor_id') === null ? null : (int) $this->getRawOriginal('actor_id'),
            'actor_name' => $this->getRawOriginal('actor_name'),
            'action' => $this->getRawOriginal('action'),
            'subject_type' => $this->getRawOriginal('subject_type'),
            'subject_id' => $this->getRawOriginal('subject_id') === null ? null : (int) $this->getRawOriginal('subject_id'),
            'properties' => $properties === null ? null : json_decode($properties, true),
            'ip' => $this->getRawOriginal('ip'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
