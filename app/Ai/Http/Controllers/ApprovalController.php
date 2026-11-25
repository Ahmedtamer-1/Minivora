<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Approvals\ApprovalService;
use App\Ai\Models\Approval;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard endpoints for reviewing, approving, and rejecting AI act-level proposals. FR-AI-07
 */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly ApprovalService $approvals,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->currentStore->getOrFail();

        $query = Approval::query()->latest('id');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, ['pending', 'approved', 'rejected', 'executed'], true)) {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function approve(Request $request, Approval $approval): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $limit = $request->input('manager_approval_limit');
        $parsedLimit = is_numeric($limit) ? (int) $limit : null;

        try {
            $updated = $this->approvals->approve($approval, $user, $parsedLimit);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function reject(Request $request, Approval $approval): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->approvals->reject($approval, $user);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }
}
