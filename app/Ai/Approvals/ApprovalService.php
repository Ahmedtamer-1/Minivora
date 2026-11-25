<?php

namespace App\Ai\Approvals;

use App\Ai\Models\Approval;
use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\Permissions\Permission;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Enforces the hard approval gate for every act-level AI function and records all approval
 * decisions and executions in the tamper-evident activity log. FR-AI-07, FR-AI-10, NFR-AIQ-08, V-15
 */
class ApprovalService
{
    /** @var list<string> */
    public const ACT_FUNCTIONS = [
        'spend_budget',
        'change_price',
        'issue_refund',
        'publish_page',
        'message_customers',
        'approve_campaign',
    ];

    public const DEFAULT_MANAGER_LIMIT = 100_000; // 1,000 EGP in piasters

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly CurrentStore $currentStore,
    ) {}

    public function isActFunction(string $functionName): bool
    {
        return in_array($functionName, self::ACT_FUNCTIONS, true);
    }

    /**
     * Create a pending approval proposal for an act-level function.
     *
     * @param  array<string, mixed>  $payload
     */
    public function requestApproval(
        string $agent,
        string $functionName,
        array $payload,
        int $estimatedImpact = 0,
        ?User $requester = null,
    ): Approval {
        $storeId = $this->currentStore->getOrFail()->getKey();

        $approval = Approval::query()->create([
            'store_id' => $storeId,
            'agent' => $agent,
            'function_name' => $functionName,
            'payload' => $payload,
            'estimated_impact' => $estimatedImpact,
            'status' => 'pending',
            'requested_by' => $requester?->getKey(),
        ]);

        $this->activity->log(
            'ai.approval_requested',
            $approval,
            [
                'agent' => $agent,
                'function' => $functionName,
                'estimated_impact' => $estimatedImpact,
            ],
            Actor::agent($agent, $requester?->getKey()),
            $storeId,
        );

        return $approval;
    }

    /**
     * Hard gate for act-level functions. Refuses execution 100% of the time without an explicit,
     * matching, approved Approval record and logs every refusal and execution. V-15, FR-AI-10
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException
     */
    public function executeActFunction(
        string $agent,
        string $functionName,
        array $payload,
        ?Approval $approval,
        callable $handler,
    ): mixed {
        $storeId = $this->currentStore->id() ?? $approval?->store_id;

        if ($approval === null) {
            return $this->refuse($agent, $functionName, 'missing_approval', null, $storeId);
        }

        if ($approval->status !== 'approved') {
            return $this->refuse($agent, $functionName, "approval_status_{$approval->status}", $approval, $storeId);
        }

        if ($approval->function_name !== $functionName) {
            return $this->refuse($agent, $functionName, 'function_mismatch', $approval, $storeId);
        }

        if ($storeId !== null && (int) $approval->store_id !== (int) $storeId) {
            return $this->refuse($agent, $functionName, 'store_mismatch', $approval, $storeId);
        }

        $result = $handler($payload, $approval);
        $storedResult = is_array($result) ? $result : ['output' => $result];

        $approval->forceFill([
            'status' => 'executed',
            'result' => $storedResult,
        ])->save();

        $this->activity->log(
            'ai.act_executed',
            $approval,
            [
                'agent' => $agent,
                'function' => $functionName,
                'approval_id' => $approval->getKey(),
                'result' => $storedResult,
            ],
            Actor::agent($agent, $approval->decided_by),
            (int) $approval->store_id,
        );

        return $result;
    }

    /**
     * Approve a pending proposal. Owners can approve any amount; managers only up to their limit;
     * staff and accountants are always denied (403). FR-AI-07
     *
     * @throws AuthorizationException
     */
    public function approve(Approval $approval, User $decider, ?int $managerApprovalLimit = null): Approval
    {
        $role = $decider->roleIn($approval->store_id);

        if ($role === null || ! $decider->canIn($approval->store_id, Permission::AiApprove)) {
            throw new AuthorizationException('You do not have permission to approve AI actions.');
        }

        if ($role === Role::Manager) {
            $limit = $managerApprovalLimit ?? (int) config('minivora.ai.manager_approval_limit', self::DEFAULT_MANAGER_LIMIT);
            if (abs($approval->estimated_impact) > $limit) {
                throw new AuthorizationException("Approval impact exceeds manager limit ({$limit}).");
            }
        } elseif ($role !== Role::Owner) {
            throw new AuthorizationException('Only store owners and authorized managers can approve AI actions.');
        }

        $approval->forceFill([
            'status' => 'approved',
            'decided_by' => $decider->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();

        $this->activity->log(
            'ai.approval_approved',
            $approval,
            [
                'agent' => $approval->agent,
                'function' => $approval->function_name,
                'estimated_impact' => $approval->estimated_impact,
            ],
            Actor::user($decider->getKey()),
            (int) $approval->store_id,
        );

        return $approval->refresh();
    }

    /**
     * Reject a pending proposal. FR-AI-07
     *
     * @throws AuthorizationException
     */
    public function reject(Approval $approval, User $decider): Approval
    {
        if (! $decider->canIn($approval->store_id, Permission::AiApprove)) {
            throw new AuthorizationException('You do not have permission to reject AI actions.');
        }

        $approval->forceFill([
            'status' => 'rejected',
            'decided_by' => $decider->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();

        $this->activity->log(
            'ai.approval_rejected',
            $approval,
            [
                'agent' => $approval->agent,
                'function' => $approval->function_name,
            ],
            Actor::user($decider->getKey()),
            (int) $approval->store_id,
        );

        return $approval->refresh();
    }

    /**
     * @throws AuthorizationException
     */
    private function refuse(string $agent, string $functionName, string $reason, ?Approval $approval, ?int $storeId): never
    {
        $this->activity->log(
            'ai.act_refused',
            $approval,
            [
                'agent' => $agent,
                'function' => $functionName,
                'reason' => $reason,
                'approval_id' => $approval?->getKey(),
            ],
            Actor::agent($agent),
            $storeId,
        );

        throw new AuthorizationException("Act-level function '{$functionName}' refused: {$reason}.");
    }
}
