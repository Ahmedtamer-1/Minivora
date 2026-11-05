<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard language: the user's saved choice, else the session, else the browser, else Arabic.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('minivora.locales');

        /** @var User|null $user */
        $user = $request->user();

        $locale = $user?->locale
            ?? $request->session()->get('locale')
            ?? $request->getPreferredLanguage($supported)
            ?? 'ar';

        app()->setLocale(in_array($locale, $supported, true) ? $locale : 'ar');

        return $next($request);
    }
}
