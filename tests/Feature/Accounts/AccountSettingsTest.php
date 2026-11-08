<?php

use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('every member can open their own account settings', function () {
    [$user] = signedInMember(Role::Staff);

    $this->get(route('account.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/account')
        ->where('account.email', $user->email));
});

test('changing name and language saves; changing email asks to verify again', function () {
    Notification::fake();
    [$user] = signedInMember();

    $this->put(route('account.update'), ['name' => 'Nour', 'email' => $user->email, 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('Nour')->and($user->fresh()->locale)->toBe('en')->and($user->fresh()->email_verified_at)->not->toBeNull();

    $this->put(route('account.update'), ['name' => 'Nour', 'email' => 'NEW@example.com', 'locale' => 'en'])->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new@example.com')->and($user->fresh()->email_verified_at)->toBeNull();
});

test('the email must not belong to someone else', function () {
    [$user] = signedInMember();
    [$other] = [User::factory()->create()];
    $this->actingAs($user);

    $this->put(route('account.update'), ['name' => 'A', 'email' => $other->email, 'locale' => 'ar'])->assertSessionHasErrors('email');
});

test('changing the password needs the current one', function () {
    [$user] = signedInMember(Role::Owner, ['password' => 'old-password-1']);

    $this->put(route('account.password'), ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasErrors('current_password');

    $this->put(route('account.password'), ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeTrue();
});
