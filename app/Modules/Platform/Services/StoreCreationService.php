<?php

namespace App\Modules\Platform\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\AuditLog\Actor;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Events\StoreCreated;
use App\Modules\Platform\Models\Store;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the owner account (if new), the store and the owner membership in one transaction.
 * FR-ONB-01, FR-ONB-03, FR-ONB-08, FR-ACC-07
 */
class StoreCreationService
{
    public function __construct(
        private readonly SubdomainService $subdomains,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * New owner: account + store together. The only way an owner account is created (FR-ACC-07).
     */
    public function createWithNewOwner(string $storeName, string $subdomain, string $email, string $password, string $locale): Store
    {
        [$store, $owner] = DB::transaction(function () use ($storeName, $subdomain, $email, $password, $locale) {
            $owner = User::query()->create([
                'name' => $storeName,
                'email' => strtolower($email),
                'password' => $password,
                'locale' => $locale,
            ]);

            return [$this->createStoreFor($owner, $storeName, $subdomain, $locale), $owner];
        });

        event(new Registered($owner));   // queues the verification email (FR-ONB-07)
        event(new StoreCreated($store, $owner));

        return $store;
    }

    /**
     * Logged-in user creates another store from the store switcher. FR-ONB-08
     */
    public function createForExistingUser(User $owner, string $storeName, string $subdomain, string $locale): Store
    {
        $store = DB::transaction(fn () => $this->createStoreFor($owner, $storeName, $subdomain, $locale));

        event(new StoreCreated($store, $owner));

        return $store;
    }

    private function createStoreFor(User $owner, string $storeName, string $subdomain, string $locale): Store
    {
        $subdomain = strtolower($subdomain);

        if (! $this->subdomains->isAvailable($subdomain)) {
            throw ValidationException::withMessages([
                'subdomain' => __('validation.subdomain_unavailable'),
            ]);
        }

        $store = Store::query()->create([
            'name' => $storeName,
            'subdomain' => $subdomain,
            'default_locale' => $locale,
            'created_by' => $owner->getKey(),
        ]);

        StoreMember::query()->create([
            'store_id' => $store->getKey(),
            'user_id' => $owner->getKey(),
            'role' => Role::Owner,
        ]);

        $this->activity->log('store.created', $store, ['subdomain' => $subdomain], Actor::user($owner->getKey()), $store->getKey());

        return $store;
    }
}
