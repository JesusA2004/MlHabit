<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('profile page is displayed', function () {
    $user = User::factory()->create(['phone' => '5512345678']);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('phone', '5512345678')
            ->where('emailVerified', true));
});

test('profile information can be updated and a new email must be verified again', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Laura Pérez', 'email' => 'Laura@Example.com', 'phone' => '+52 55 1234 5678'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->name)->toBe('Laura Pérez')
        ->and($user->email)->toBe('laura@example.com')
        ->and($user->phone)->toBe('+52 55 1234 5678')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Otro Nombre', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('the email must be unique and the phone valid', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'X', 'email' => $other->email, 'phone' => 'llámame'])
        ->assertSessionHasErrors(['email', 'phone']);
});

test('accounts cannot be self deleted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});
