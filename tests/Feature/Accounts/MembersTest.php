<?php

use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Models\Invitation;
use App\Modules\Accounts\Models\StoreMember;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Notifications\StoreInvitationNotification;
use App\Modules\Platform\Models\Store;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/** Invite someone and return the plain token from the sent email. */
function inviteAndGetToken(string $email, Role $role): string
{
    $token = null;
    test()->post(route('members.invite'), ['email' => $email, 'role' => $role->value])->assertSessionHasNoErrors();

    Notification::assertSentTo(new AnonymousNotifiable, StoreInvitationNotification::class, function ($n, $channels, $notifiable) use ($email, &$token) {
        if ($notifiable->routes['mail'] === $email) {
            $token = $n->plainToken;
        }

        return true;
    });

    return $token;
}

test('FR-ACC-04 owners invite staff by email with a role; a new person joins by setting a password', function () {
    Notification::fake();
    [, $store] = signedInMember();

    $token = inviteAndGetToken('packer@example.com', Role::Staff);
    $invitation = Invitation::withoutStoreScope()->sole();
    expect($invitation->token_hash)->toBe(hash('sha256', $token)); // only the hash is stored

    Auth::logout();
    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->component('auth/invitation')->where('valid', true)->where('hasAccount', false));

    $this->post(route('invitations.accept', $token), ['name' => 'Packer', 'password' => 'packer-pass-1'])
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'packer@example.com')->sole();
    expect($user->roleIn($store))->toBe(Role::Staff)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    // A used link cannot be used again.
    $this->get(route('invitations.show', $token))->assertInertia(fn (Assert $page) => $page->where('valid', false));
});

test('FR-ACC-04 an existing user accepts an invitation after signing in', function () {
    Notification::fake();
    [, $store] = signedInMember();
    $existing = User::factory()->create(['email' => 'manager@example.com']);

    $token = inviteAndGetToken('manager@example.com', Role::Manager);

    $this->actingAs($existing)->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));
    expect($existing->roleIn($store))->toBe(Role::Manager);
});

test('FR-ACC-04 owners change roles and revoke access, and a store keeps its last owner', function () {
    [$owner, $store] = signedInMember();
    $staff = User::factory()->create();
    $member = StoreMember::withoutStoreScope()->create(['store_id' => $store->id, 'user_id' => $staff->id, 'role' => Role::Staff]);
    $ownerMember = StoreMember::withoutStoreScope()->where('user_id', $owner->id)->sole();

    $this->put(route('members.update', $member->id), ['role' => 'accountant'])->assertSessionHasNoErrors();
    expect($staff->roleIn($store))->toBe(Role::Accountant);

    $this->delete(route('members.destroy', $member->id))->assertSessionHasNoErrors();
    expect($staff->roleIn($store))->toBeNull();

    $this->put(route('members.update', $ownerMember->id), ['role' => 'manager'])->assertSessionHasErrors('member');
    $this->delete(route('members.destroy', $ownerMember->id))->assertSessionHasErrors('member');

    expect(ActivityLog::query()->where('store_id', $store->id)->pluck('action')->all())
        ->toContain('member.role_changed', 'member.revoked');
});

test('FR-ACC-04 owners cannot touch members of another store', function () {
    signedInMember();
    $otherStore = Store::factory()->withMember(User::factory()->create())->create();
    $foreign = StoreMember::withoutStoreScope()->where('store_id', $otherStore->id)->sole();

    $this->delete(route('members.destroy', $foreign->id))->assertNotFound();
    expect(StoreMember::withoutStoreScope()->whereKey($foreign->id)->exists())->toBeTrue();
});

test('FR-ACC-03 only owners manage staff, settings and the activity log', function (Role $role) {
    signedInMember($role);

    $this->get(route('members.index'))->assertForbidden();
    $this->get(route('settings.general'))->assertForbidden();
    $this->get(route('settings.activity'))->assertForbidden();
    $this->post(route('members.invite'), ['email' => 'x@example.com', 'role' => 'staff'])->assertForbidden();
    $this->get(route('dashboard'))->assertOk();
})->with([Role::Manager, Role::Accountant, Role::Staff]);

test('FR-ACC-03 staff and accountants cannot publish; managers can', function () {
    signedInMember(Role::Staff);
    $this->post(route('settings.publish'))->assertForbidden();

    signedInMember(Role::Accountant);
    $this->post(route('settings.publish'))->assertForbidden();

    [, $store] = signedInMember(Role::Manager);
    $this->post(route('settings.publish'))->assertSessionHasNoErrors();
    expect($store->fresh()->isLive())->toBeTrue();
});
