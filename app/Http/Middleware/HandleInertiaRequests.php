<?php

namespace App\Http\Middleware;

use App\Core\Permissions\PermissionMatrix;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            // The whole dictionary is ~40 KB: send it once per language and file version, and let the
            // client reuse it on every later visit instead of downloading it with each click.
            'translations' => Inertia::once(fn () => $this->translations($locale))
                ->as('translations:'.$locale.':'.@filemtime(lang_path($locale.'.json'))),
            'domain' => config('minivora.domain'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'is_platform_admin' => $user->is_platform_admin,
                ],
            ],
            // Lazy: the store is resolved by route middleware that runs after this one.
            'store' => fn () => $this->currentStore($user),
            'stores' => fn () => $user?->stores()->orderBy('name')->get(['stores.id', 'stores.name', 'stores.subdomain'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'subdomain' => $s->subdomain])->all() ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function currentStore(?User $user): ?array
    {
        $store = app(CurrentStore::class)->get();

        if ($store === null || $user === null) {
            return null;
        }

        $role = $user->roleIn($store);

        return [
            'id' => $store->id,
            'name' => $store->name,
            'subdomain' => $store->subdomain,
            'url' => $store->url(),
            'status' => $store->status()->value,
            'role' => $role?->value,
            'permissions' => $role === null ? [] : PermissionMatrix::for($role),
        ];
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
