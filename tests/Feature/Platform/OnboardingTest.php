<?php

use App\Core\Permissions\Role;
use App\Modules\Platform\Events\StoreProfileUpdated;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('FR-ONB-04 the owner answers up to three optional questions and the Store Builder is told', function () {
    Event::fake([StoreProfileUpdated::class]);
    [, $store] = signedInMember();

    $this->post(route('onboarding.save'), ['sells' => 'Handmade leather bags', 'location' => 'Cairo', 'language' => 'ar'])
        ->assertRedirect(route('dashboard'));

    $store->refresh();
    expect($store->profile)->toBe(['sells' => 'Handmade leather bags', 'location' => 'Cairo', 'language' => 'ar'])
        ->and($store->default_locale)->toBe('ar')
        ->and($store->onboarding_completed_at)->not->toBeNull();

    Event::assertDispatched(StoreProfileUpdated::class, fn ($e) => $e->store->is($store) && $e->profile->sells === 'Handmade leather bags');

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('showOnboarding', false));
});

test('FR-ONB-04 every question can be skipped', function () {
    Event::fake([StoreProfileUpdated::class]);
    [, $store] = signedInMember();

    $this->post(route('onboarding.save'), ['sells' => 'Coffee'])->assertSessionHasNoErrors();
    expect($store->fresh()->profile)->toBe(['sells' => 'Coffee', 'location' => null, 'language' => null]);

    [, $other] = signedInMember();
    $this->post(route('onboarding.skip'))->assertRedirect(route('dashboard'));
    expect($other->fresh()->onboarding_completed_at)->not->toBeNull()
        ->and($other->fresh()->profile)->toBeNull();
});

test('FR-ONB-04 the language answer must be Arabic or English', function () {
    signedInMember();

    $this->post(route('onboarding.save'), ['language' => 'fr'])->assertSessionHasErrors('language');
});

test('FR-ONB-10 the dashboard shows the setup checklist with the next step', function () {
    signedInMember(Role::Owner, ['email_verified_at' => null]);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('checklist.total', 5)
        ->where('checklist.done', 0)
        ->where('checklist.next', 'verify_email')
        ->where('checklist.steps.1.key', 'add_products')
        ->where('checklist.steps.4.key', 'publish'));
});
