<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification runs in the background; owners keep building meanwhile. FR-ONB-07
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $user->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : Inertia::render('auth/verify-email', ['email' => $user->email]);
    }

    /**
     * Signed link from the email. The owner may open it signed out or on another device, so the
     * user comes from the link, not the session; the signature proves it.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route($request->user() !== null ? 'dashboard' : 'login')
            ->with('success', __('dashboard.verify.done'));
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', __('dashboard.verify.sent'));
    }
}
