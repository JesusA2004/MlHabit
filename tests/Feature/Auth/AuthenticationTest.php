<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('login screen can be rendered', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->where('canResetPassword', true));
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password'])->assertNotFound();
});

test('each role lands on its own area after login', function (UserRole $role, string $home) {
    $user = User::factory()->role($role)->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($home);

    $this->assertAuthenticatedAs($user);
})->with([
    'super admin' => [UserRole::SuperAdmin, '/admin'],
    'comercial' => [UserRole::Sales, '/admin'],
    'finanzas' => [UserRole::Finance, '/admin'],
    'cliente' => [UserRole::Customer, '/portal'],
    'técnico' => [UserRole::Technician, '/tecnico'],
]);

test('an intended url outside the role area is ignored', function () {
    $customer = User::factory()->role(UserRole::Customer)->create();

    $this->withSession(['url.intended' => url('/admin/usuarios')])
        ->post('/login', ['email' => $customer->email, 'password' => 'password'])
        ->assertRedirect('/portal');
});

test('an intended url inside the role area is kept', function () {
    $staff = User::factory()->role(UserRole::Sales)->create();

    $this->withSession(['url.intended' => url('/admin/prospectos?estado=new')])
        ->post('/login', ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(url('/admin/prospectos?estado=new'));
});

test('login is case insensitive on the email and records last_login_at', function () {
    $user = User::factory()->role(UserRole::Sales)->create(['email' => 'carla@mlhabitat.mx', 'last_login_at' => null]);

    $this->post('/login', ['email' => 'Carla@MLHabitat.mx', 'password' => 'password'])->assertRedirect('/admin');

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('inactive users cannot log in and get the generic error', function () {
    $user = User::factory()->role(UserRole::Sales)->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
});

test('unknown emails get exactly the same error', function () {
    $this->post('/login', ['email' => 'nadie@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

test('login attempts are rate limited per email and ip', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [strtolower($user->email).'|127.0.0.1'])), amount: 5);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('staff users are redirected away from the portal and technician areas only by their modules', function () {
    // El área /admin rechaza a clientes; el resto de áreas lo controla cada módulo.
    $customer = User::factory()->role(UserRole::Customer)->create();

    $this->actingAs($customer)->get('/admin/usuarios')->assertForbidden();
});
