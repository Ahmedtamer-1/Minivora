<?php

namespace App\Core\Tenancy;

use App\Modules\Accounts\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for dashboard routes: uses the logged-in member's selected store. Falls back to the
 * first store the user belongs to; sends users with no store to "create a store".
 */
class ResolveStoreFromSession
{
    public const SESSION_KEY = 'current_store_id';

    public function __construct(private readonly CurrentStore $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $selectedId = $request->session()->get(self::SESSION_KEY);

        $store = $selectedId !== null
            ? $user->stores()->whereKey($selectedId)->first()
            : null;

        $store ??= $user->stores()->orderBy('store_members.created_at')->first();

        if ($store === null) {
            return $user->is_platform_admin
                ? redirect()->route('admin.stores.index')
                : redirect()->route('stores.create');
        }

        $request->session()->put(self::SESSION_KEY, $store->getKey());
        $this->current->set($store);

        return $next($request);
    }
}
