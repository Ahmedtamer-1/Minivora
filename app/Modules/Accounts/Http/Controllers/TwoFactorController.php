<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * TOTP two-factor authentication management for store owners and managers. FR-ACC-06
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly TotpService $totp,
        private readonly ActivityLogger $activity,
    ) {}

    public function setup(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeEligibleRole($user);

        $secret = $this->totp->generateSecret();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $payload = [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->provisioningUri($user->email, $secret),
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('two_factor_setup', $payload);
    }

    public function confirm(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeEligibleRole($user);

        $data = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if ($user->two_factor_secret === null || ! $this->totp->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        $this->activity->log('account.two_factor_enabled', $user);

        if ($request->expectsJson()) {
            return response()->json(['enabled' => true]);
        }

        return back()->with('success', 'Two-factor authentication has been enabled.');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeEligibleRole($user);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => __('auth.account.wrong_password'),
            ]);
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->activity->log('account.two_factor_disabled', $user);

        if ($request->expectsJson()) {
            return response()->json(['enabled' => false]);
        }

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    private function authorizeEligibleRole(User $user): void
    {
        $store = $this->currentStore->get();
        $role = $store !== null ? $user->roleIn($store) : null;

        abort_unless(in_array($role, [Role::Owner, Role::Manager], true), 403);
    }
}
