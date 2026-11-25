<?php

use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Modules\Accounts\Services\TotpService;
use Illuminate\Support\Facades\DB;

test('FR-ACC-06: owner and manager can setup, confirm, and disable TOTP 2FA with audit logs', function (Role $role) {
    [$user] = signedInMember($role, ['password' => 'secret-pass-1']);
    $totp = app(TotpService::class);

    // 1. Setup returns secret & otpauth URI and stores unconfirmed encrypted secret.
    $setup = $this->postJson(route('account.two-factor.setup'))
        ->assertOk()
        ->assertJsonStructure(['secret', 'otpauth_uri']);

    $secret = $setup->json('secret');
    $uri = $setup->json('otpauth_uri');

    expect($secret)->toBeString()->not->toBeEmpty()
        ->and($uri)->toStartWith('otpauth://totp/Minivora:')
        ->and($uri)->toContain('secret='.$secret);

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toBe($secret)
        ->and($user->toArray())->not->toHaveKey('two_factor_secret');

    // 2. Invalid TOTP code is rejected.
    $this->postJson(route('account.two-factor.confirm'), ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();

    // 3. Valid TOTP code confirms 2FA and writes activity log.
    $validCode = $totp->currentCode($secret);
    $this->postJson(route('account.two-factor.confirm'), ['code' => $validCode])
        ->assertOk()
        ->assertJson(['enabled' => true]);

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'account.two_factor_enabled')->where('subject_id', $user->id)->exists())->toBeTrue();

    // 4. Disabling 2FA rejects wrong password.
    $this->deleteJson(route('account.two-factor.destroy'), ['current_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    // 5. Disabling 2FA with valid password clears secret & confirmation and writes activity log.
    $this->deleteJson(route('account.two-factor.destroy'), ['current_password' => 'secret-pass-1'])
        ->assertOk()
        ->assertJson(['enabled' => false]);

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'account.two_factor_disabled')->where('subject_id', $user->id)->exists())->toBeTrue();
})->with([
    'owner' => [Role::Owner],
    'manager' => [Role::Manager],
]);

test('FR-ACC-06: non-JSON 2FA setup redirects back with flash data', function () {
    [$user] = signedInMember(Role::Owner);

    $this->from(route('account.edit'))
        ->post(route('account.two-factor.setup'))
        ->assertRedirect(route('account.edit'))
        ->assertSessionHas('two_factor_setup');

    expect($user->fresh()->two_factor_secret)->not->toBeNull()
        ->and($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('FR-ACC-06: staff and accountant receive 403 on all 2FA endpoints', function (Role $role) {
    signedInMember($role, ['password' => 'secret-pass-1']);

    $this->postJson(route('account.two-factor.setup'))->assertForbidden();
    $this->postJson(route('account.two-factor.confirm'), ['code' => '123456'])->assertForbidden();
    $this->deleteJson(route('account.two-factor.destroy'), ['current_password' => 'secret-pass-1'])->assertForbidden();
})->with([
    'staff' => [Role::Staff],
    'accountant' => [Role::Accountant],
]);

test('FR-ACC-09: any signed-in user can download their PDPL data export without exposing secrets', function () {
    [$user, $store] = signedInMember(Role::Owner, ['password' => 'export-pass-1']);
    $totp = app(TotpService::class);
    $secret = $totp->generateSecret();
    $user->two_factor_secret = $secret;
    $user->two_factor_confirmed_at = now();
    $user->save();

    $response = $this->get(route('account.export'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="minivora-account-data.json"');

    $raw = $response->getContent();
    $payload = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

    expect($payload['profile'])->toMatchArray([
        'name' => $user->name,
        'email' => $user->email,
        'locale' => $user->locale,
    ])
        ->and($payload['profile'])->toHaveKeys(['name', 'email', 'locale', 'created_at', 'deletion_requested_at'])
        ->and($payload['profile'])->not->toHaveKey('password')
        ->and($payload['profile'])->not->toHaveKey('two_factor_secret')
        ->and($payload['memberships'])->toHaveCount(1)
        ->and($payload['memberships'][0]['store_id'])->toBe($store->id)
        ->and($payload['memberships'][0]['role'])->toBe(Role::Owner->value)
        ->and($payload['activity_logs'])->not->toBeEmpty()
        ->and($raw)->not->toContain($secret)
        ->and($raw)->not->toContain($user->getAuthPassword())
        ->and(ActivityLog::query()->where('action', 'account.data_exported')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('FR-ACC-09: any signed-in user can submit a 30-day account deletion request with password confirmation', function () {
    [$user] = signedInMember(Role::Staff, ['password' => 'delete-pass-1']);

    $this->post(route('account.delete-request'), ['current_password' => 'wrong-pass'])
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()->deletion_requested_at)->toBeNull();

    $this->from(route('account.edit'))
        ->post(route('account.delete-request'), ['current_password' => 'delete-pass-1'])
        ->assertRedirect(route('account.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $user->refresh();
    expect($user->deletion_requested_at)->not->toBeNull();

    $log = ActivityLog::query()
        ->where('action', 'account.deletion_requested')
        ->where('subject_id', $user->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties)->toHaveKey('due_by')
        ->and($log->properties['due_by'])->toBe($user->deletion_requested_at->copy()->addDays(30)->toIso8601String());
});
