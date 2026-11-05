<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreUrl;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Services\StoreLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Store settings: name, language, opening-soon password, publish. FR-ONB-06, FR-ONB-07
 */
class StoreSettingsController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly StoreLifecycleService $lifecycle,
    ) {}

    public function edit(): Response
    {
        $store = $this->current->getOrFail();

        return Inertia::render('settings/index', [
            'settings' => [
                'name' => $store->name,
                'subdomain' => $store->subdomain,
                'default_locale' => $store->default_locale,
                'has_storefront_password' => $store->storefront_password !== null,
                'published_at' => $store->published_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'default_locale' => ['required', Rule::in(config('minivora.locales'))],
        ]);

        $this->lifecycle->updateGeneral($this->current->getOrFail(), $data['name'], $data['default_locale']);

        return back()->with('success', __('dashboard.saved'));
    }

    public function updateStorefrontPassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['nullable', 'string', 'min:4', 'max:64']]);

        $this->lifecycle->setStorefrontPassword($this->current->getOrFail(), $data['password'] ?? null);

        return back()->with('success', __('dashboard.saved'));
    }

    public function publish(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->lifecycle->publish($this->current->getOrFail(), $user);

        return back()->with('success', __('dashboard.publish.done'));
    }

    public function unpublish(): RedirectResponse
    {
        $this->lifecycle->unpublish($this->current->getOrFail());

        return back()->with('success', __('dashboard.publish.undone'));
    }

    /**
     * Opens the storefront with a short-lived signed link that unlocks "opening soon" for this
     * browser. Works across subdomains without sharing cookies. FR-ONB-06
     */
    public function preview(): RedirectResponse
    {
        $store = $this->current->getOrFail();

        $relative = URL::temporarySignedRoute('storefront.preview', now()->addMinutes(5), ['store' => $store->subdomain], absolute: false);

        return redirect()->away(StoreUrl::base($store->subdomain).$relative);
    }
}
