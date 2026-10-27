<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

function platformCurrentOtp(User $user): string
{
    return (new Google2FA)->getCurrentOtp(decrypt((string) $user->two_factor_secret));
}

beforeEach(function () {
    $this->withoutVite();
});

test('enabling 2FA requires a recent password confirmation', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect('/user/confirm-password');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

test('a user can enable, confirm and see recovery codes', function () {
    $user = User::factory()->role(UserRole::Sales)->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->post('/user/two-factor-authentication')->assertRedirect();
    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->getJson('/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg', 'url']);

    // Un código incorrecto no confirma.
    $this->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');

    $this->post('/user/confirmed-two-factor-authentication', ['code' => platformCurrentOtp($user)])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->getJson('/user/two-factor-recovery-codes')->assertOk()->assertJsonCount(8);
});

test('login with 2FA goes through the challenge and lands on the role home', function () {
    $user = User::factory()->role(UserRole::Technician)->create();
    $provider = app(TwoFactorAuthenticationProvider::class);
    $user->forceFill([
        'two_factor_secret' => encrypt($provider->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-recuperacion-1', 'codigo-recuperacion-2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge')
        ->assertSessionHas('login.id', $user->id);
    $this->assertGuest();

    $this->get('/two-factor-challenge')->assertOk();

    $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => platformCurrentOtp($user)])->assertRedirect('/tecnico');
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('a recovery code works once in the challenge', function () {
    $user = User::factory()->role(UserRole::Finance)->create();
    $user->forceFill([
        'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-recuperacion-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['recovery_code' => 'codigo-recuperacion-1'])->assertRedirect('/admin');

    expect(json_decode(decrypt($user->refresh()->two_factor_recovery_codes), true))->not->toContain('codigo-recuperacion-1');
});

test('an inactive user with 2FA is stopped before the challenge', function () {
    $user = User::factory()->role(UserRole::Sales)->inactive()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('x'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('login.id');
});
