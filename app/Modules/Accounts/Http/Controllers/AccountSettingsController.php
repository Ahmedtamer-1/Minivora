<?php

namespace App\Modules\Accounts\Http\Controllers;

use App\Core\AuditLog\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
