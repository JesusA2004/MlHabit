<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\RolePermission;
use App\Models\User;
use App\Notifications\Platform\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('only users with users.manage can open the users screen', function () {
    $this->get('/admin/usuarios')->assertRedirect('/login');

    $this->actingAs(F::user(UserRole::Sales))->get('/admin/usuarios')->assertForbidden();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->get('/admin/usuarios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/usuarios/Index')
            ->has('users.data')
            ->where('filters.tipo', 'equipo'));
});

test('the list filters by type, role and status', function () {
    $admin = F::user(UserRole::SuperAdmin, ['name' => 'Ada Admin']);
    F::user(UserRole::Sales, ['name' => 'Carla Comercial']);
    F::user(UserRole::Sales, ['name' => 'Beto Inactivo', 'is_active' => false]);
    F::user(UserRole::Customer, ['name' => 'Cliente Uno']);
    F::user(UserRole::Technician, ['name' => 'Técnico Uno']);

    $this->actingAs($admin)
        ->get('/admin/usuarios?rol=comercial&estado=activos')
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Carla Comercial'));

    $this->actingAs($admin)
        ->get('/admin/usuarios?tipo=clientes')
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Cliente Uno'));
});

test('inviting a staff user sends the branded invitation and audits it', function () {
    Notification::fake();
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)
        ->post('/admin/usuarios', [
            'name' => 'Diana Diseño',
            'email' => 'Diana@MLHabitat.mx',
            'role' => 'diseno',
            'phone' => '55 1234 5678',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $user = User::query()->where('email', 'diana@mlhabitat.mx')->firstOrFail();
    expect($user->role)->toBe(UserRole::Design)
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Te damos la bienvenida a ML Habitat'
            && str_contains((string) $mail->actionUrl, '/invitacion/'.$user->id)
            && str_contains((string) $mail->actionUrl, 'signature=');
    });

    expect(ActivityLog::query()->where('action', 'usuario.invitado')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('customers and technicians cannot be created from the staff invitation', function () {
    Notification::fake();
    $admin = F::user(UserRole::SuperAdmin);

    foreach (['cliente', 'tecnico'] as $role) {
        $this->actingAs($admin)
            ->post('/admin/usuarios', ['name' => 'X', 'email' => "{$role}@example.com", 'role' => $role])
            ->assertSessionHasErrors('role');
    }

    Notification::assertNothingSent();
});

test('only a super admin can invite another super admin', function () {
    Notification::fake();
    $director = F::user(UserRole::Direction);
    // Dirección no tiene users.manage por defecto; se le otorga para probar la regla.
    RolePermission::sync(UserRole::Direction, ['dashboard.view', 'users.manage']);

    $this->actingAs($director)
        ->post('/admin/usuarios', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'super_admin'])
        ->assertSessionHasErrors('role');
});

test('the invitation link opens the activation screen and sets the password', function () {
    $user = F::user(UserRole::Sales, ['email_verified_at' => null, 'last_login_at' => null]);

    $url = URL::temporarySignedRoute('invitacion.aceptar', now()->addHours(72), ['user' => $user->id]);

    $response = $this->get($url);
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/AcceptInvitation')
        ->where('email', $user->email)
        ->where('action', '/reset-password')
        ->has('token'));

    $token = $response->inertiaProps('token');

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'una-frase-larga-y-segura',
        'password_confirmation' => 'una-frase-larga-y-segura',
    ])->assertRedirect('/login');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'una-frase-larga-y-segura'])
        ->assertRedirect('/admin');
});

test('unsigned or expired invitation links are rejected', function () {
    $user = F::user(UserRole::Sales);

    $this->get('/invitacion/'.$user->id)->assertForbidden();

    $expired = URL::temporarySignedRoute('invitacion.aceptar', now()->subMinute(), ['user' => $user->id]);
    $this->get($expired)->assertForbidden();
});

test('an admin can change role and deactivate a user, which ends their sessions', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $target = F::user(UserRole::Sales);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);

    $this->actingAs($admin)
        ->patch("/admin/usuarios/{$target->id}", ['role' => 'operaciones', 'is_active' => false])
        ->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->role)->toBe(UserRole::Operations)
        ->and($target->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    $log = ActivityLog::query()->where('action', 'usuario.actualizado')->firstOrFail();
    expect($log->changes['before']['role'])->toBe('comercial')
        ->and($log->changes['after']['role'])->toBe('operaciones');
});

test('users cannot demote or deactivate themselves', function () {
    $admin = F::user(UserRole::SuperAdmin);
    F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)
        ->patch("/admin/usuarios/{$admin->id}", ['role' => 'comercial'])
        ->assertSessionHasErrors('role');

    $this->actingAs($admin)
        ->patch("/admin/usuarios/{$admin->id}", ['is_active' => false])
        ->assertSessionHasErrors('is_active');

    expect($admin->refresh()->role)->toBe(UserRole::SuperAdmin)->and($admin->is_active)->toBeTrue();
});

test('the last active super admin can never be demoted or deactivated', function () {
    $admin = F::user(UserRole::SuperAdmin);
    F::user(UserRole::SuperAdmin, ['is_active' => false]);

    $this->actingAs($admin)->patch("/admin/usuarios/{$admin->id}", ['role' => 'direccion'])->assertSessionHasErrors('role');
    $this->actingAs($admin)->patch("/admin/usuarios/{$admin->id}", ['is_active' => false])->assertSessionHasErrors('is_active');

    // Un rol sin superadministración (aunque tenga users.manage) no puede tocarlo.
    RolePermission::sync(UserRole::Direction, ['dashboard.view', 'users.manage']);
    $director = F::user(UserRole::Direction);
    $this->actingAs($director)->patch("/admin/usuarios/{$admin->id}", ['is_active' => false])->assertForbidden();
    $this->actingAs($director)->patch("/admin/usuarios/{$admin->id}", ['role' => 'comercial'])->assertForbidden();

    expect(User::query()->where('role', 'super_admin')->where('is_active', true)->count())->toBe(1);
});

test('with two super admins one can demote the other but never leave zero', function () {
    $a = F::user(UserRole::SuperAdmin);
    $b = F::user(UserRole::SuperAdmin);

    $this->actingAs($a)->patch("/admin/usuarios/{$b->id}", ['role' => 'direccion'])->assertSessionHasNoErrors();
    expect($b->refresh()->role)->toBe(UserRole::Direction);

    $this->actingAs($a)->patch("/admin/usuarios/{$a->id}", ['is_active' => false])->assertSessionHasErrors('is_active');
    expect(User::query()->where('role', 'super_admin')->where('is_active', true)->count())->toBe(1);
});

test('resending an invitation only works for pending accounts', function () {
    Notification::fake();
    $admin = F::user(UserRole::SuperAdmin);
    $pending = F::user(UserRole::Sales, ['last_login_at' => null]);
    $active = F::user(UserRole::Sales, ['last_login_at' => now()]);

    $this->actingAs($admin)->post("/admin/usuarios/{$pending->id}/invitacion")->assertSessionHas('success');
    $this->actingAs($admin)->post("/admin/usuarios/{$active->id}/invitacion")->assertSessionHas('error');

    Notification::assertSentTo($pending, UserInvitation::class);
    Notification::assertNotSentTo($active, UserInvitation::class);
});

test('an admin can reset another user two factor authentication', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $target = F::user(UserRole::Finance);
    $target->forceFill([
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(['a'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($admin)->delete("/admin/usuarios/{$target->id}/dos-pasos")->assertSessionHas('success');

    $target->refresh();
    expect($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_confirmed_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'usuario.dos_pasos_restablecido')->exists())->toBeTrue();
});

test('users cannot be hard deleted from the admin', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $target = F::user(UserRole::Sales);

    $this->actingAs($admin)->delete("/admin/usuarios/{$target->id}")->assertStatus(405);
    expect(User::query()->whereKey($target->id)->exists())->toBeTrue();
});
