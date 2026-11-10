<?php

namespace App\Http\Middleware;

use App\Core\Permissions\Permission;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `store.can:members.manage`. Checks the SRS §7.2 matrix for the current store.
 * Several permissions (`store.can:ledger.view,budgets.view`) let the member in with any one of them.
 */
class EnsureStorePermission
{
    public function __construct(private readonly CurrentStore $current) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $store = $this->current->get();

        abort_unless($user !== null && $store !== null, 403);
        $allowed = array_filter($permissions, fn (string $p) => $user->canIn($store, Permission::from($p)));
        abort_if($allowed === [], 403);

        return $next($request);
    }
}
