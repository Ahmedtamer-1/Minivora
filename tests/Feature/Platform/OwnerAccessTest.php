<?php

use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Notification;

test('FR-ONB-06 a signed-in member sees their opening-soon store; strangers see the opening-soon page', function () {
    [$owner, $store] = signedInMember(userState: ['email_verified_at' => null]);

    $this->get(storeUrl($store->subdomain, '/en'))->assertOk()->assertSee('Only your team can see this')->assertDontSee('Opening soon');

    auth()->logout();
    $this->flushSession();
    $this->get(storeUrl($store->subdomain, '/en'))->assertOk()->assertSee('Opening soon');

    // Someone signed in to a different store is still a stranger here.
    $other = User::factory()->create();
    Store::factory()->withMember($other)->create();
    $this->actingAs($other)->get(storeUrl($store->subdomain, '/en'))->assertSee('Opening soon');
});

test('user:verify-email verifies an account so its owner can publish', function () {
    $user = User::factory()->create(['email' => 'Owner@Example.com', 'email_verified_at' => null]);

    $this->artisan('user:verify-email', ['email' => 'owner@example.com'])->assertSuccessful();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->artisan('user:verify-email', ['email' => 'nobody@example.com'])->assertFailed();
});

test('resending the verification email says so when mail cannot be sent', function () {
    [$user] = signedInMember(userState: ['email_verified_at' => null]);
    Notification::shouldReceive('send')->andThrow(new RuntimeException('Connection could not be established'));
    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('Connection could not be established'));

    $this->post(route('verification.send'))->assertRedirect()->assertSessionHas('error');
});
