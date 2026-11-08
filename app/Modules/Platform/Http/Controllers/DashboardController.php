<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\StoreProfileData;
use App\Modules\Platform\Services\SetupChecklistService;
use App\Modules\Platform\Services\StoreProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard home: optional onboarding questions (FR-ONB-04), setup checklist (FR-ONB-10) and the
 * store preview. KPIs and the AI "Today" feed land here later.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly SetupChecklistService $checklist,
        private readonly StoreProfileService $profiles,
    ) {}

    public function home(): Response
    {
        $store = $this->current->getOrFail();

        return Inertia::render('today/index', [
            'showOnboarding' => $store->onboarding_completed_at === null,
            'profile' => $store->profileData()->toArray(),
            'checklist' => $this->checklist->for($store),
            'previewUrl' => route('store.preview'),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $profile = StoreProfileData::validateAndCreate($request->only('sells', 'location', 'language'));

        $this->profiles->update($this->current->getOrFail(), $profile);

        return redirect()->route('dashboard')->with('success', __('dashboard.onboarding.saved'));
    }

    public function skipProfile(): RedirectResponse
    {
        $this->profiles->skip($this->current->getOrFail());

        return redirect()->route('dashboard');
    }
}
