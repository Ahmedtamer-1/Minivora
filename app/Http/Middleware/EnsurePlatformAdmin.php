<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user?->is_platform_admin === true, 403);

        return $next($request);
    }
}
