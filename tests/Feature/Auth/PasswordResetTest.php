<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('reset password link screen can be rendered', function () {
    $this->get('/forgot-password')->assertOk()->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
});

test('reset password link can be requested', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', trans('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

test('requesting a link for an unknown email looks exactly the same', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nadie@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', trans('passwords.sent'));

    Notification::assertNothingSent();
});

test('reset password screen can be rendered', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $this->get('/reset-password/'.$notification->token)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/ResetPassword')->has('token'));

        return true;
    });
});

test('password can be reset with valid token and verifies the email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'una-frase-larga-y-segura',
            'password_confirmation' => 'una-frase-larga-y-segura',
        ])->assertSessionHasNoErrors()->assertRedirect('/login');

        return true;
    });

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});
