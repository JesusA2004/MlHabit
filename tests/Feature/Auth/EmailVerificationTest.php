<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('unverified staff are sent to the verification notice', function () {
    $user = User::factory()->role(UserRole::Sales)->unverified()->create();

    $this->actingAs($user)->get('/admin')->assertRedirect('/email/verify');

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
});

test('email can be verified with the signed link', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->role(UserRole::Sales)->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect();

    Event::assertDispatched(Verified::class);
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('otro@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('confirm password screen can be rendered', function () {
    $this->actingAs(User::factory()->create())
        ->get('/user/confirm-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ConfirmPassword'));
});
