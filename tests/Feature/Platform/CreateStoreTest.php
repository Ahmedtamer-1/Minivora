<?php

use App\Core\Permissions\Role;
use App\Core\Tenancy\ResolveStoreFromSession;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Notifications\VerifyEmailQueued;
use App\Modules\Platform\Events\StoreCreated;
use App\Modules\Platform\Models\Store;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

$form = fn (array $overrides = []) => [
    'store_name' => 'Brown Tote',
    'subdomain' => 'browntote',
    'email' => 'owner@example.com',
    'password' => 'secret-pass-1',
    ...$overrides,
];

test('FR-ONB-01 the landing page offers one create-your-store form to guests', function () {
    $this->get('http://minivora.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('onboarding/create-store')->where('mode', 'new-owner'));
});

test('FR-ONB-01 submitting the form creates the account and the store together', function () use ($form) {
    $this->post('http://minivora.test/create-store', $form())
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'owner@example.com')->sole();
    $store = Store::query()->where('subdomain', 'browntote')->sole();

    expect($store->name)->toBe('Brown Tote')
        ->and($store->created_by)->toBe($user->id)
        ->and($user->roleIn($store))->toBe(Role::Owner)
        ->and($store->published_at)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('FR-ONB-03 the owner lands inside the new store dashboard right away', function () use ($form) {
    $start = microtime(true);

    $this->post('http://minivora.test/create-store', $form())->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('today/index')
            ->where('store.subdomain', 'browntote')
            ->where('store.role', 'owner')
            ->where('showOnboarding', true));

    expect(microtime(true) - $start)->toBeLessThan(60);
});

test('FR-ONB-02 reserved and taken addresses are refused', function () use ($form) {
    Store::factory()->create(['subdomain' => 'taken']);

    $this->post('http://minivora.test/create-store', $form(['subdomain' => 'admin']))->assertSessionHasErrors('subdomain');
    $this->post('http://minivora.test/create-store', $form(['subdomain' => 'taken']))->assertSessionHasErrors('subdomain');
    $this->post('http://minivora.test/create-store', $form(['subdomain' => 'Bad Name!']))->assertSessionHasErrors('subdomain');

    expect(User::query()->count())->toBe(0);
});

test('FR-ONB-02 the address check answers as the owner types, with suggestions when taken', function () {
    Store::factory()->create(['subdomain' => 'adorna']);

    $this->getJson('http://minivora.test/api/v1/subdomains/check?name=Adorna')
        ->assertOk()
        ->assertJson(['subdomain' => 'adorna', 'status' => 'taken', 'available' => false])
        ->assertJsonCount(3, 'suggestions');

    $this->getJson('http://minivora.test/api/v1/subdomains/check?name=www')
        ->assertJson(['status' => 'reserved', 'available' => false]);

    $this->getJson('http://minivora.test/api/v1/subdomains/check?name=Nile%20Crafts')
        ->assertJson(['subdomain' => 'nile-crafts', 'status' => 'available', 'available' => true, 'suggestions' => []]);
});

test('FR-ONB-07 the verification email goes out in the background', function () use ($form) {
    Notification::fake();

    $this->post('http://minivora.test/create-store', $form());

    $user = User::query()->where('email', 'owner@example.com')->sole();
    Notification::assertSentTo($user, VerifyEmailQueued::class);
    expect(new VerifyEmailQueued)->toBeInstanceOf(ShouldQueue::class);
});

test('FR-ONB-07 an unverified owner can build but cannot publish', function () {
    [$user, $store] = signedInMember(Role::Owner, ['email_verified_at' => null]);

    $this->get(route('dashboard'))->assertOk();
    $this->post(route('settings.publish'))->assertSessionHasErrors('publish');
    expect($store->fresh()->published_at)->toBeNull();

    $user->markEmailAsVerified();
    $this->post(route('settings.publish'))->assertSessionHasNoErrors();
    expect($store->fresh()->published_at)->not->toBeNull();
});

test('FR-ONB-08 a signed-in owner creates another store with the same account and switches', function () {
    Event::fake([StoreCreated::class]);
    [$user, $first] = signedInMember();

    $this->post(route('stores.store'), ['store_name' => 'Second Shop', 'subdomain' => 'second-shop'])
        ->assertRedirect(route('dashboard'));

    $second = Store::query()->where('subdomain', 'second-shop')->sole();
    expect($user->roleIn($second))->toBe(Role::Owner)
        ->and(User::query()->count())->toBe(1)
        ->and(session(ResolveStoreFromSession::SESSION_KEY))->toBe($second->id);

    Event::assertDispatched(StoreCreated::class);

    $this->post(route('stores.switch', $first->id))->assertRedirect(route('dashboard'));
    expect(session(ResolveStoreFromSession::SESSION_KEY))->toBe($first->id);
});

test('FR-ONB-08 users cannot switch into a store they do not belong to', function () {
    signedInMember();
    $other = Store::factory()->create();

    $this->post(route('stores.switch', $other->id))->assertNotFound();
});

test('FR-ACC-07 there is no separate sign-up page', function () {
    $this->get('http://minivora.test/register')->assertNotFound();
});
