<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Tenancy\ResolveStoreFromSession;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Services\StoreCreationService;
use App\Modules\Platform\Services\SubdomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-step "Create your store": account + store together, then the guided first run (OnboardingFlowController).
 * FR-ONB-01, FR-ONB-02, FR-ONB-03, FR-ONB-08, FR-ACC-07
 */
class StoreSignupController extends Controller
{
    public function __construct(
        private readonly StoreCreationService $creation,
        private readonly SubdomainService $subdomains,
    ) {}

    /** Landing page with the create-your-store form (guests) or the dashboard (signed in). */
    public function landing(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/create-store', ['mode' => 'new-owner']);
    }

    /** Live availability check as the owner types. FR-ONB-02 */
    public function check(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        return response()->json($this->subdomains->check((string) $request->query('name')));
    }

    /** Guest: create the owner account and the store in one step. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:80'],
            'subdomain' => ['required', 'string', 'max:63'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $this->ensureAvailable($data['subdomain']);

        $store = $this->creation->createWithNewOwner(
            $data['store_name'],
            strtolower($data['subdomain']),
            $data['email'],
            $data['password'],
            app()->getLocale(),
        );

        $owner = User::query()->findOrFail($store->created_by);
        Auth::login($owner, remember: true);
        $request->session()->regenerate();
        $request->session()->put(ResolveStoreFromSession::SESSION_KEY, $store->id);

        // Straight into the guided first run; every step can be skipped to the dashboard.
        return redirect()->route('onboarding.describe');
    }

    /** Signed in: "Create another store" from the store switcher. FR-ONB-08 */
    public function create(): Response
    {
        return Inertia::render('onboarding/create-store', ['mode' => 'existing-owner']);
    }

    public function storeForUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:80'],
            'subdomain' => ['required', 'string', 'max:63'],
        ]);

        $this->ensureAvailable($data['subdomain']);

        /** @var User $user */
        $user = $request->user();
        $store = $this->creation->createForExistingUser($user, $data['store_name'], strtolower($data['subdomain']), app()->getLocale());

        $request->session()->put(ResolveStoreFromSession::SESSION_KEY, $store->id);

        return redirect()->route('dashboard')->with('success', __('dashboard.stores.created'));
    }

    public function switch(Request $request, int $storeId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->stores()->whereKey($storeId)->exists(), 404);

        $request->session()->put(ResolveStoreFromSession::SESSION_KEY, $storeId);

        return redirect()->route('dashboard');
    }

    private function ensureAvailable(string $subdomain): void
    {
        $subdomain = strtolower($subdomain);
        $status = $this->subdomains->status($subdomain);

        if ($status !== SubdomainService::OK) {
            throw ValidationException::withMessages([
                'subdomain' => __('validation.subdomain_'.$status),
            ]);
        }
    }
}
