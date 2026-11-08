<?php

namespace App\Modules\Accounts\Services;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Email + password sign-in, locked for 15 minutes after 5 failures per account and IP. NFR-SEC-02
 */
class LoginService
{
    public function attempt(string $email, string $password, bool $remember, string $ip): void
    {
        $key = $this->throttleKey($email, $ip);
        $max = (int) config('minivora.login.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            event(new Lockout(request()));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        if (! Auth::attempt(['email' => strtolower($email), 'password' => $password], $remember)) {
            RateLimiter::hit($key, (int) config('minivora.login.decay_seconds'));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'login:'.strtolower($email).'|'.$ip;
    }
}
