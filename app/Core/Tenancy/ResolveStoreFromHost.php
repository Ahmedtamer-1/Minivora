<?php

namespace App\Core\Tenancy;

use App\Modules\Platform\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for storefront routes: finds the store from the {store} subdomain route parameter
 * (seller's own domain later). FR-ONB-02
 */
class ResolveStoreFromHost
{
    public function __construct(private readonly CurrentStore $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = strtolower((string) $request->route('store'));

        $store = Store::query()->where('subdomain', $subdomain)->first();

        abort_if($store === null, 404);

        $this->current->set($store);

        // The subdomain parameter is resolved; controllers should not receive it.
        $request->route()?->forgetParameter('store');

        return $next($request);
    }
}
