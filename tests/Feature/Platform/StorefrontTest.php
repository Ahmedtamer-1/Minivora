<?php

use App\Modules\Platform\Models\Store;
use Inertia\Testing\AssertableInertia;

test('FR-ONB-06 a new store shows an opening-soon page until it is published', function () {
    $store = Store::factory()->create(['subdomain' => 'soon', 'name' => 'Soon Shop']);

    $this->get(storeUrl('soon', '/en'))
        ->assertOk()
        ->assertSee('Opening soon')
        ->assertSee('Soon Shop')
        ->assertDontSee('Products are on their way');

    $store->update(['published_at' => now()]);

    $this->get(storeUrl('soon', '/en'))->assertOk()->assertSee('Products are on their way');
});

test('FR-ONB-06 the store password unlocks the opening-soon page', function () {
    Store::factory()->create(['subdomain' => 'locked', 'storefront_password' => 'letmein']);

    $this->post(storeUrl('locked', '/__unlock'), ['password' => 'nope'])->assertSessionHasErrors('password');
    $this->post(storeUrl('locked', '/__unlock'), ['password' => 'letmein'])->assertRedirect('/en');
    $this->get(storeUrl('locked', '/en'))->assertSee('Products are on their way');
});

test('FR-ONB-06 the owner previews the draft store through a signed link', function () {
    [, $store] = signedInMember();

    $redirect = $this->get(route('store.preview'))->assertRedirect()->headers->get('Location');
    expect($redirect)->toStartWith('http://'.$store->subdomain.'.minivora.test/__preview?');

    $this->get($redirect)->assertRedirect('/en');
    $this->get(storeUrl($store->subdomain, '/en'))->assertSee('Products are on their way');

    // Tampered or unsigned links do nothing.
    $this->flushSession();
    $this->get(storeUrl($store->subdomain, '/__preview'))->assertForbidden();
});

test('FR-ACC-08 a suspended store is unavailable to shoppers', function () {
    Store::factory()->live()->suspended()->create(['subdomain' => 'paused']);

    $this->get(storeUrl('paused', '/en'))->assertStatus(503)->assertSee('Store unavailable');
});

test('FR-CAT-15 FR-SEO-22 storefront pages set lang and dir, defaulting to the store language', function () {
    Store::factory()->live()->create(['subdomain' => 'arabic', 'default_locale' => 'ar']);

    $this->get(storeUrl('arabic', '/'))->assertRedirect('/ar');
    $this->get(storeUrl('arabic', '/ar'))->assertSee('lang="ar" dir="rtl"', false);
    $this->get(storeUrl('arabic', '/en'))->assertSee('lang="en" dir="ltr"', false);
});

test('FR-ONB-02 unknown and reserved subdomains are never storefronts', function () {
    $this->get(storeUrl('nobody', '/en'))->assertNotFound();

    // app.<domain> serves the platform, not a store.
    $this->get('http://app.minivora.test/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('onboarding/create-store'));
});
