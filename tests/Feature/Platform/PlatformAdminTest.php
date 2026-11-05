<?php

use App\Core\AuditLog\ActivityLog;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Inertia\Testing\AssertableInertia as Assert;

test('FR-ACC-08 platform admins list stores with usage', function () {
    $admin = User::factory()->platformAdmin()->create();
    $owner = User::factory()->create();
    Store::factory()->withMember($owner)->create(['name' => 'Adorna EG', 'subdomain' => 'adorna']);
    Store::factory()->live()->create();

    $this->actingAs($admin)->get(route('admin.stores.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform-admin/index')
            ->where('totals.stores', 2)
            ->where('totals.live', 1)
            ->has('stores.data', 2));

    $this->actingAs($admin)->get(route('admin.stores.index', ['q' => 'adorna']))
        ->assertInertia(fn (Assert $page) => $page->has('stores.data', 1)->where('stores.data.0.members_count', 1));
});

test('FR-ACC-08 platform admins suspend and restore stores, and it is logged', function () {
    $admin = User::factory()->platformAdmin()->create();
    $store = Store::factory()->live()->create();

    $this->actingAs($admin)->post(route('admin.stores.suspend', $store), ['reason' => 'Unpaid'])->assertRedirect();
    expect($store->fresh()->isSuspended())->toBeTrue()->and($store->fresh()->suspended_reason)->toBe('Unpaid');

    $this->actingAs($admin)->post(route('admin.stores.restore', $store))->assertRedirect();
    expect($store->fresh()->isSuspended())->toBeFalse();

    expect(ActivityLog::query()->where('store_id', $store->id)->pluck('action')->all())
        ->toBe(['store.suspended', 'store.restored']);
});

test('FR-ACC-08 store owners are not platform admins', function () {
    signedInMember();

    $this->get(route('admin.stores.index'))->assertForbidden();
});

test('FR-ACC-08 a platform admin with no store of their own lands on the admin page', function () {
    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.stores.index'));
});
