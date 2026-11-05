<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Platform\Enums\StoreStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront access by store status:
 * - suspended: 503 page (FR-ACC-08)
 * - opening soon: password page, unless unlocked by password or the owner's preview link (FR-ONB-06)
 * - live: open
 */
class StorefrontGate
{
    public function __construct(private readonly CurrentStore $current) {}

    public static function sessionKey(int $storeId): string
    {
        return 'storefront_unlocked.'.$storeId;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->current->getOrFail();
        $locale = $this->localeFor($request, $store->default_locale);
        app()->setLocale($locale);

        if ($request->routeIs('storefront.preview', 'storefront.unlock')) {
            return $next($request);
        }

        return match ($store->status()) {
            StoreStatus::Suspended => response()->view('storefront.pages.suspended', ['store' => $store], 503),
            StoreStatus::OpeningSoon => $request->session()->get(self::sessionKey($store->id)) === true
                ? $next($request)
                : response()->view('storefront.pages.opening-soon', ['store' => $store]),
            StoreStatus::Live => $next($request),
        };
    }

    private function localeFor(Request $request, string $default): string
    {
        $segment = $request->segment(1);

        return in_array($segment, config('minivora.locales'), true) ? $segment : $default;
    }
}
