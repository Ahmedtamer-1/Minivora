<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Services\StoreLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform admin: list, suspend and restore stores, with usage. FR-ACC-08
 */
class PlatformAdminController extends Controller
{
    public function __construct(private readonly StoreLifecycleService $lifecycle) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $stores = Store::query()
            ->withCount('members')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('subdomain', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'subdomain' => $store->subdomain,
                'url' => $store->url(),
                'status' => $store->status()->value,
                'members_count' => $store->members_count,
                'suspended_reason' => $store->suspended_reason,
                'created_at' => $store->created_at->toIso8601String(),
            ]);

        return Inertia::render('platform-admin/index', [
            'stores' => $stores,
            'q' => $search,
            'totals' => [
                'stores' => Store::query()->count(),
                'live' => Store::query()->whereNotNull('published_at')->whereNull('suspended_at')->count(),
                'suspended' => Store::query()->whereNotNull('suspended_at')->count(),
            ],
        ]);
    }

    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->lifecycle->suspend($store, $data['reason'] ?? null);

        return back()->with('success', __('dashboard.admin.suspended'));
    }

    public function restore(Store $store): RedirectResponse
    {
        $this->lifecycle->restore($store);

        return back()->with('success', __('dashboard.admin.restored'));
    }
}
