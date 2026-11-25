<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ReturnRequest;
use App\Modules\Orders\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dashboard & storefront: list, submit, approve and reject return requests. FR-ORD-08
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::query()->with(['order', 'refund'])->latest('id');
        $status = $request->query('status');
        if (is_string($status) && in_array($status, ReturnRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        return response()->json([
            'return_requests' => $query->paginate(25),
        ]);
    }

    public function store(Request $request, int $order): JsonResponse
    {
        $model = Order::query()->findOrFail($order);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(ReturnRequest::TYPES)],
            'reason' => ['required', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $returnRequest = $this->returns->requestReturn(
            $model,
            $data['type'] ?? 'return',
            $data['reason'],
            isset($data['refund_amount']) ? (int) $data['refund_amount'] : null,
        );

        return response()->json(['return_request' => $returnRequest], 201);
    }

    public function approve(Request $request, int $returnRequest): JsonResponse
    {
        $model = ReturnRequest::query()->findOrFail($returnRequest);
        /** @var User $reviewer */
        $reviewer = $request->user();

        $approved = $this->returns->approve($model, $reviewer);

        return response()->json(['return_request' => $approved]);
    }

    public function reject(Request $request, int $returnRequest): JsonResponse
    {
        $model = ReturnRequest::query()->findOrFail($returnRequest);
        /** @var User $reviewer */
        $reviewer = $request->user();

        $rejected = $this->returns->reject($model, $reviewer);

        return response()->json(['return_request' => $rejected]);
    }
}
