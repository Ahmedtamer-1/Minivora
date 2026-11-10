<?php

use App\Core\Permissions\Role;
use App\Modules\Platform\Models\StoreSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('FR-ONB-04 the guided first run walks describe → building → theme → ready', function () {
    [, $store] = signedInMember();

    $this->get(route('onboarding.describe'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('onboarding/describe'));

    $this->post(route('onboarding.describe.save'), ['description' => 'Egyptian-inspired jewelry in Cairo'])
        ->assertRedirect(route('onboarding.building'));
    expect($store->fresh()->profile['sells'])->toBe('Egyptian-inspired jewelry in Cairo');

    $this->get(route('onboarding.building'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('onboarding/building')
        ->where('description', 'Egyptian-inspired jewelry in Cairo'));

    $this->get(route('onboarding.theme'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('onboarding/theme')->where('theme', null));

    $this->post(route('onboarding.theme.save'), ['palette' => 'emerald', 'radius' => 12])->assertRedirect(route('onboarding.ready'));
    expect(StoreSetting::query()->where('store_id', $store->id)->where('key', 'theme')->value('value'))->toBe(['palette' => 'emerald', 'radius' => 12]);

    $this->get(route('onboarding.ready'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('onboarding/ready'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('showOnboarding', false));
});

test('FR-ONB-04 the description is required and the theme must be one we offer', function () {
    signedInMember();

    $this->post(route('onboarding.describe.save'), ['description' => ''])->assertSessionHasErrors('description');
    $this->post(route('onboarding.theme.save'), ['palette' => 'neon', 'radius' => 99])->assertSessionHasErrors(['palette', 'radius']);
});

test('the first run is for members who can design the store', function () {
    signedInMember(Role::Staff);

    $this->get(route('onboarding.describe'))->assertForbidden();
});
