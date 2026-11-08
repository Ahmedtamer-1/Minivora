<?php

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\URL;

test('NFR-SEC-02 users sign in with email and password; passwords are hashed', function () {
    $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse']);

    expect($user->getAuthPassword())->not->toBe('correct-horse')
        ->and(password_get_info($user->getAuthPassword())['algoName'])->toBeIn(['bcrypt', 'argon2id']);

    $this->post('http://minivora.test/login', ['email' => 'owner@example.com', 'password' => 'correct-horse'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('NFR-SEC-02 sign-in locks for 15 minutes after 5 failures per account and IP', function () {
    User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse']);

    foreach (range(1, 5) as $_) {
        $this->post('http://minivora.test/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    }

    // Even the right password is refused while locked.
    $this->post('http://minivora.test/login', ['email' => 'owner@example.com', 'password' => 'correct-horse'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->travel(16)->minutes();
    $this->post('http://minivora.test/login', ['email' => 'owner@example.com', 'password' => 'correct-horse'])->assertRedirect(route('dashboard'));
});

test('NFR-SEC-01 pages send security headers', function () {
    $this->get('http://minivora.test/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
});

test('FR-ONB-07 the emailed link verifies the address even when signed out', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id, 'hash' => sha1($user->email),
    ]);

    $this->get($url)->assertRedirect(route('login'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
