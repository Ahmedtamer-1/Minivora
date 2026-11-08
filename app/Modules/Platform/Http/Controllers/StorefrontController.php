<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Http\Middleware\StorefrontGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Storefront shell: language-prefixed home, opening-soon unlock and owner preview.
 * Catalog pages are added by the Catalog module. FR-CAT-15, FR-ONB-06, FR-SEO-22
 */
class StorefrontController extends Controller
{
    public function __construct(private readonly CurrentStore $current) {}

    public function root(): RedirectResponse
    {
        return redirect('/'.$this->current->getOrFail()->default_locale);
    }

    public function home(): View
    {
        return view('storefront.pages.home', ['store' => $this->current->getOrFail()]);
    }

    /** Signed link from the dashboard: unlock this browser and go home. */
    public function preview(Request $request): RedirectResponse
    {
        $store = $this->current->getOrFail();
        $request->session()->put(StorefrontGate::sessionKey($store->id), true);

        return redirect('/'.$store->default_locale);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $store = $this->current->getOrFail();
        $request->validate(['password' => ['required', 'string']]);

        $key = 'storefront-unlock:'.$store->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['password' => __('storefront.opening_soon.too_many')]);
        }

        if ($store->storefront_password === null || ! Hash::check((string) $request->input('password'), $store->storefront_password)) {
            RateLimiter::hit($key, 15 * 60);

            return back()->withErrors(['password' => __('storefront.opening_soon.wrong_password')]);
        }

        RateLimiter::clear($key);
        $request->session()->put(StorefrontGate::sessionKey($store->id), true);

        return redirect('/'.$store->default_locale);
    }
}
