<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(fn () => $this->withoutVite());

/** Invita al cliente y devuelve la URL firmada del correo (mecanismo de platform). */
function inviteAndCaptureUrl(Customer $customer): string
{
    Notification::fake();
    test()->actingAs(Fixtures::staff())->post(route('admin.clientes.invitacion', $customer))->assertSessionHas('success');

    $user = User::query()->where('email', $customer->email)->firstOrFail();
    $url = null;
    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $n) use ($user, &$url) {
        $url = $n->toMail($user)->actionUrl;

        return true;
    });
    auth()->logout();

    return (string) $url;
}

it('invita a un cliente: crea cuenta cliente sin contraseña usable y la liga', function () {
    $customer = Customer::factory()->create(['email' => 'ana@example.com']);

    inviteAndCaptureUrl($customer);

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->email_verified_at)->toBeNull()
        ->and($customer->refresh()->user_id)->toBe($user->id)
        ->and(Hash::check('password', $user->password))->toBeFalse();
});

it('reenviar no duplica la cuenta', function () {
    $customer = Customer::factory()->create(['email' => 'rita@example.com']);

    inviteAndCaptureUrl($customer);
    inviteAndCaptureUrl($customer->refresh());

    expect(User::query()->where('email', 'rita@example.com')->count())->toBe(1);
});

it('acepta la invitación: crea contraseña, verifica correo y entra al portal', function () {
    $customer = Customer::factory()->create(['email' => 'luis@example.com']);
    $url = inviteAndCaptureUrl($customer);

    $token = null;
    $this->get($url)->assertOk()->assertInertia(function (Assert $page) use (&$token) {
        $page->component('auth/AcceptInvitation')->where('email', 'luis@example.com');
        $token = $page->toArray()['props']['token'];
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'luis@example.com',
        'password' => 'Una-clave-segura-123',
        'password_confirmation' => 'Una-clave-segura-123',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'luis@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Una-clave-segura-123', $user->password))->toBeTrue();

    $this->post(route('login.store'), ['email' => 'luis@example.com', 'password' => 'Una-clave-segura-123'])->assertRedirect('/portal');
    $this->get('/portal')->assertOk();
});

it('la invitación vence a las 72 horas', function () {
    $customer = Customer::factory()->create(['email' => 'eva@example.com']);
    $url = inviteAndCaptureUrl($customer);

    $this->travel(73)->hours();

    $this->get($url)->assertForbidden();
});

it('un enlace manipulado no sirve', function () {
    $customer = Customer::factory()->create(['email' => 'max@example.com']);
    $url = inviteAndCaptureUrl($customer);

    $this->get($url.'x')->assertForbidden();
});

it('no invita si el correo pertenece a personal', function () {
    $staff = Fixtures::staff();
    $customer = Customer::factory()->create(['email' => $staff->email]);

    $this->actingAs(Fixtures::staff())->post(route('admin.clientes.invitacion', $customer))->assertSessionHasErrors('email');
});

it('requiere customers.manage para invitar', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(Fixtures::staff(UserRole::Finance))->post(route('admin.clientes.invitacion', $customer))->assertForbidden();
});

it('no existe registro público', function () {
    $this->get('/register')->assertNotFound();
});
