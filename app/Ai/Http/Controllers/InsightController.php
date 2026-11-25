<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Approvals\ApprovalService;
use App\Ai\Models\Insight;
use App\Ai\Proactive\FeedRanker;
use App\Ai\Proactive\MorningBrief;
use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard endpoints for the ranked proactive insights feed, insight actions (approve / snooze /
 * dismiss), and the daily Morning Brief. FR-PRO-04, FR-PRO-05, FR-PRO-06
 */
class InsightController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly FeedRanker $ranker,
        private readonly MorningBrief $morningBrief,
        private readonly ApprovalService $approvals,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore->getOrFail();
        $cap = (int) $request->query('limit', (string) FeedRanker::DEFAULT_DAILY_CAP);

        return response()->json([
            'data' => $this->ranker->rank($store, max(1, min(20, $cap))),
        ]);
    }

    /**
     * Act on an insight card: approve, snooze, or dismiss. FR-PRO-05
     */
    public function act(Request $request, Insight $insight): JsonResponse
    {
        $store = $this->currentStore->getOrFail();

        $data = $request->validate([
            'action' => ['required', 'in:approve,snooze,dismiss'],
            'snooze_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        $action = (string) $data['action'];
        $approval = null;

        if ($action === 'approve') {
            $insight->forceFill(['status' => 'approved'])->save();

            $rec = $insight->recommended_action ?? [];
            $fn = isset($rec['function']) && is_string($rec['function']) ? $rec['function'] : null;
            if ($fn !== null && $this->approvals->isActFunction($fn)) {
                $approval = $this->approvals->requestApproval(
                    $insight->agent,
                    $fn,
                    (array) ($rec['payload'] ?? []),
                    (int) $insight->estimated_impact,
                    $user,
                );
            }
        } elseif ($action === 'snooze') {
            $hours = (int) ($data['snooze_hours'] ?? 24);
            $insight->forceFill([
                'status' => 'snoozed',
                'snoozed_until' => Carbon::now()->addHours($hours),
            ])->save();
        } else {
            $insight->forceFill(['status' => 'dismissed'])->save();
        }

        $this->activity->log(
            "ai.insight_{$action}",
            $insight,
            [
                'insight_id' => $insight->getKey(),
                'agent' => $insight->agent,
                'type' => $insight->type,
                'approval_id' => $approval?->getKey(),
            ],
            $user !== null ? Actor::user($user->getKey()) : Actor::system(),
            $store->getKey(),
        );

        return response()->json([
            'data' => $insight->refresh(),
            'approval' => $approval,
        ]);
    }

    public function morningBrief(Request $request): JsonResponse
    {
        $store = $this->currentStore->getOrFail();

        return response()->json([
            'data' => $this->morningBrief->compile($store),
        ]);
    }
}
