<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\AuditLog\ActivityLog;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's activity log: who did what, including (later) which AI agent acted. FR-ACC-05, FR-AI-10
 */
class ActivityController extends Controller
{
    public function index(CurrentStore $current): Response
    {
        $logs = ActivityLog::query()
            ->where('store_id', $current->id())
            ->latest('id')
            ->paginate(50);

        $users = User::query()->whereIn('id', $logs->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        return Inertia::render('settings/activity', [
            'logs' => $logs->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_type' => $log->actor_type->value,
                'actor' => $log->actor_name ?? ($log->actor_id !== null ? $users[$log->actor_id] ?? null : null),
                'on_behalf_of' => $log->actor_name !== null && $log->actor_id !== null ? $users[$log->actor_id] ?? null : null,
                'properties' => $log->properties,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
        ]);
    }
}
