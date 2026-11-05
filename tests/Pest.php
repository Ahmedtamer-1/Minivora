<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\ResolveStoreFromSession;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Isolation', 'Unit');

/**
 * A user who belongs to a fresh store with the given role, signed in with that store selected.
 *
 * @return array{0: User, 1: Store}
 */
function signedInMember(Role $role = Role::Owner, array $userState = []): array
{
    $user = User::factory()->create($userState);
    $store = Store::factory()->withMember($user, $role)->create();

    test()->actingAs($user)->withSession([ResolveStoreFromSession::SESSION_KEY => $store->id]);

    return [$user, $store];
}

/** Absolute URL on a store's subdomain (test domain is minivora.test). */
function storeUrl(string $subdomain, string $path = '/'): string
{
    return 'http://'.$subdomain.'.minivora.test'.$path;
}
