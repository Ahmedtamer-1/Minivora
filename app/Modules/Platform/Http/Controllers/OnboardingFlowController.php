<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\StoreProfileData;
use App\Modules\Platform\Models\StoreSetting;
use App\Modules\Platform\Services\StoreProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The guided first run after "Create your store": describe it → watch it build → make it yours → ready.
 * FR-ONB-04, FR-ONB-05
 *
 * Placeholder: the "building" step is a scripted preview. Nothing here calls the AI team or the
 * builder yet; the description is saved on the store profile (where the Store Builder agent will
 * read it) and the theme choice is saved as a store setting. Swap the building step for the real
 * builder job when it ships.
 */
class OnboardingFlowController extends Controller
{
    public const PALETTES = ['classic', 'sand', 'emerald', 'navy', 'wine'];

    public function __construct(
        private readonly CurrentStore $current,
        private readonly StoreProfileService $profiles,
    ) {}

    public function describe(): Response
    {
        return Inertia::render('onboarding/describe', [
            'description' => $this->current->getOrFail()->profileData()->sells,
        ]);
    }

    public function saveDescription(Request $request): RedirectResponse
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:500']]);

        $store = $this->current->getOrFail();
        $profile = $store->profileData();
        $this->profiles->update($store, new StoreProfileData(trim($data['description']), $profile->location, $profile->language));

        return redirect()->route('onboarding.building');
    }

    public function building(): Response
    {
        return Inertia::render('onboarding/building', [
            'description' => $this->current->getOrFail()->profileData()->sells,
        ]);
    }

    public function theme(): Response
    {
        return Inertia::render('onboarding/theme', [
            'description' => $this->current->getOrFail()->profileData()->sells,
            'theme' => $this->savedTheme(),
        ]);
    }

    public function saveTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'palette' => ['required', Rule::in(self::PALETTES)],
            'radius' => ['required', 'integer', 'min:0', 'max:24'],
        ]);

        $store = $this->current->getOrFail();
        StoreSetting::query()->updateOrCreate(
            ['store_id' => $store->getKey(), 'key' => 'theme'],
            ['value' => ['palette' => $data['palette'], 'radius' => (int) $data['radius']]],
        );

        return redirect()->route('onboarding.ready');
    }

    public function ready(): Response
    {
        $store = $this->current->getOrFail();
        $this->profiles->skip($store); // the first run is done; the dashboard stops asking

        return Inertia::render('onboarding/ready');
    }

    /** @return array{palette: string, radius: int}|null */
    private function savedTheme(): ?array
    {
        $value = StoreSetting::query()
            ->where('store_id', $this->current->getOrFail()->getKey())
            ->where('key', 'theme')
            ->value('value');

        return is_array($value) ? $value : null;
    }
}
