<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Models\Alert;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard: list alerts, mark read, and send diagnostic test emails. FR-NTF-03, FR-NTF-04
 */
class AlertController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CurrentStore $currentStore,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Alert::query()->latest('id');

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $type = $request->query('type');
        if (is_string($type) && in_array($type, Alert::TYPES, true)) {
            $query->where('type', $type);
        }

        return response()->json([
            'alerts' => $query->paginate(25),
            'unread_count' => Alert::query()->unread()->count(),
        ]);
    }

    public function markRead(Request $request, int $alert): JsonResponse
    {
        /** @var Alert $model */
        $model = Alert::query()->findOrFail($alert);
        $model->markAsRead();

        return response()->json([
            'alert' => $model->fresh(),
        ]);
    }

    public function sendTestEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        /** @var User $staff */
        $staff = $request->user();
        $store = $this->currentStore->getOrFail();
        $toEmail = $data['email'] ?? $staff->email;

        $this->notifications->sendDiagnosticEmail($toEmail, $store, $staff);

        return response()->json([
            'status' => 'sent',
            'to' => $toEmail,
        ]);
    }
}
