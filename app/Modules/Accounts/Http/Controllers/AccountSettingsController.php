<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Core\AuditLog\ActivityLog;
use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\ActorType;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's own account: name, email, dashboard language and password. Open to every
 * member, whatever their role in the store.
 */
class AccountSettingsController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/account', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', Rule::in(['ar', 'en'])],
        ]);

        $emailChanged = mb_strtolower($data['email']) !== mb_strtolower($user->email);

        $user->name = trim($data['name']);
        $user->locale = $data['locale'];
        if ($emailChanged) {
            $user->email = mb_strtolower(trim($data['email']));
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            $this->activity->log('account.email_changed', $user);
        }
        $request->session()->put('locale', $user->locale);

        return back()->with('success', $emailChanged ? __('auth.account.email_changed') : __('auth.account.saved'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => __('auth.account.wrong_password')]);
        }

        $user->password = $data['password'];
        $user->save();

        $this->activity->log('account.password_changed', $user);

        return back()->with('success', __('auth.account.password_saved'));
    }

    /** Download the signed-in user's personal data as JSON. FR-ACC-09 */
    public function exportData(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->activity->log('account.data_exported', $user);

        $memberships = $user->stores()
            ->get(['stores.id', 'stores.name', 'stores.subdomain'])
            ->map(fn ($store): array => [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'subdomain' => $store->subdomain,
                'role' => $store->pivot->role,
                'joined_at' => $store->pivot->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $activityLogs = ActivityLog::query()
            ->where('actor_type', ActorType::User)
            ->where('actor_id', $user->id)
            ->orderByDesc('id')
            ->get(['id', 'store_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip', 'created_at'])
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'store_id' => $log->store_id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'ip' => $log->ip,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
                'deletion_requested_at' => $user->deletion_requested_at?->toIso8601String(),
            ],
            'memberships' => $memberships,
            'activity_logs' => $activityLogs,
        ];

        return response(
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="minivora-account-data.json"',
            ],
        );
    }

    /** Record a 30-day PDPL account deletion request with password confirmation. FR-ACC-09 */
    public function requestDeletion(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => __('auth.account.wrong_password')]);
        }

        $requestedAt = now();
        $user->deletion_requested_at = $requestedAt;
        $user->save();

        $this->activity->log('account.deletion_requested', $user, [
            'due_by' => $requestedAt->copy()->addDays(30)->toIso8601String(),
        ]);

        return back()->with('success', 'Your account deletion request has been recorded and will be completed within 30 days.');
    }
}
