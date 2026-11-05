<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Core\Tenancy\ResolveStoreFromSession;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Services\MembershipService;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accept a store invitation: existing users sign in, new people set a name and password. FR-ACC-04
 */
class InvitationController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->memberships->findPendingByToken($token);

        if ($invitation === null) {
            return Inertia::render('auth/invitation', ['valid' => false]);
        }

        $store = Store::query()->findOrFail($invitation->store_id);

        return Inertia::render('auth/invitation', [
            'valid' => true,
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'storeName' => $store->name,
            'hasAccount' => User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->memberships->findPendingByToken($token);
        abort_if($invitation === null, 410);

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            $existing = User::query()->where('email', $invitation->email)->first();

            if ($existing !== null) {
                // Existing account: sign in first, then come back to this link.
                $request->session()->put('url.intended', route('invitations.show', $token));

                return redirect()->route('login');
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'password' => ['required', 'string', Password::min(8)],
            ]);

            $user = DB::transaction(fn () => User::query()->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'locale' => app()->getLocale(),
            ]));

            Auth::login($user);
            $request->session()->regenerate();
        }

        $this->memberships->accept($invitation, $user);
        $request->session()->put(ResolveStoreFromSession::SESSION_KEY, $invitation->store_id);

        return redirect()->route('dashboard');
    }
}
