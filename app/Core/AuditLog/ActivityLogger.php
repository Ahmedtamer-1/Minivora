<?php

namespace App\Core\AuditLog;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes activity log rows for sensitive actions (store created, members changed, publish, and later
 * refunds, price changes and AI approvals). FR-ACC-05, NFR-SEC-10
 */
class ActivityLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private readonly CurrentStore $currentStore) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?Actor $actor = null,
        ?int $storeId = null,
    ): ActivityLog {
        $actor ??= $this->defaultActor();
        $storeId ??= $this->currentStore->id();

        return DB::transaction(function () use ($action, $subject, $properties, $actor, $storeId) {
            $previous = ActivityLog::query()->lockForUpdate()->latest('id')->first();
            $prevHash = $previous?->hash ?? self::GENESIS;

            $fields = [
                'store_id' => $storeId,
                'actor_type' => $actor->type->value,
                'actor_id' => $actor->userId,
                'actor_name' => $actor->name,
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties === [] ? null : $properties,
                'ip' => request()?->ip(),
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            return ActivityLog::query()->create([
                ...$fields,
                'properties' => $fields['properties'],
                'prev_hash' => $prevHash,
                'hash' => ActivityLog::computeHash($prevHash, $fields),
            ]);
        });
    }

    /**
     * Walk the chain and return the ids of rows whose content or link no longer matches.
     *
     * @return list<int>
     */
    public function verify(): array
    {
        $broken = [];
        $prevHash = self::GENESIS;

        ActivityLog::query()->orderBy('id')->chunk(500, function ($rows) use (&$broken, &$prevHash) {
            foreach ($rows as $row) {
                /** @var ActivityLog $row */
                $expected = ActivityLog::computeHash($prevHash, $row->hashableFields());

                if ($row->prev_hash !== $prevHash || $row->hash !== $expected) {
                    $broken[] = $row->id;
                }

                $prevHash = $row->hash;
            }
        });

        return $broken;
    }

    private function defaultActor(): Actor
    {
        $userId = auth()->id();

        return $userId !== null ? Actor::user((int) $userId) : Actor::system();
    }
}
