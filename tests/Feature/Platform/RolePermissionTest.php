<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\RolePermission;
use App\Support\Permissions\RoleDefaults;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('the roles matrix is only available with roles.manage', function () {
    $this->actingAs(F::user(UserRole::Sales))->get('/admin/roles')->assertForbidden();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->get('/admin/roles')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/Index')
            ->has('groups')
            ->has('roles', count(UserRole::cases()))
            ->where('roles.0.value', 'super_admin')
            ->where('roles.0.locked', 'all'));
});

test('permission changes apply immediately and are audited', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $sales = F::user(UserRole::Sales);

    // Antes: comercial ve prospectos.
    $this->actingAs($sales)->get('/admin/usuarios')->assertForbidden();

    $this->actingAs($admin)
        ->put('/admin/roles/comercial', ['permissions' => ['dashboard.view', 'users.manage']])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(RolePermission::map()['comercial'])->toEqualCanonicalizing(['dashboard.view', 'users.manage']);

    // Sin cerrar sesión, el cambio aplica en la siguiente petición.
    $sales->refresh();
    $this->actingAs($sales)->get('/admin/usuarios')->assertOk();

    $log = ActivityLog::query()->where('action', 'permisos.actualizados')->firstOrFail();
    expect($log->changes['role'])->toBe('comercial')
        ->and($log->changes['after']['permissions'])->toEqualCanonicalizing(['dashboard.view', 'users.manage'])
        ->and($log->changes['removed'])->toContain('leads.view')
        ->and($log->changes['added'])->toContain('users.manage');
});

test('a role can be saved with no permissions at all', function () {
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->put('/admin/roles/calidad', ['permissions' => []])
        ->assertSessionHasNoErrors();

    expect(RolePermission::map()['calidad'])->toBe([]);
});

test('unknown permissions are rejected', function () {
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->put('/admin/roles/comercial', ['permissions' => ['leads.view', 'hack.everything']])
        ->assertSessionHasErrors('permissions.1');
});

test('super admin and customer roles are locked', function () {
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)->put('/admin/roles/super_admin', ['permissions' => []])->assertStatus(422);
    $this->actingAs($admin)->put('/admin/roles/cliente', ['permissions' => ['leads.view']])->assertStatus(422);
    $this->actingAs($admin)->post('/admin/roles/cliente/restablecer')->assertStatus(422);
});

test('recommended values can be restored per role', function () {
    $admin = F::user(UserRole::SuperAdmin);
    RolePermission::sync(UserRole::Finance, []);

    $this->actingAs($admin)->post('/admin/roles/finanzas/restablecer')->assertSessionHas('success');

    expect(RolePermission::map()['finanzas'])->toEqualCanonicalizing(
        array_map(fn (Permission $p) => $p->value, RoleDefaults::for(UserRole::Finance)),
    );
    expect(ActivityLog::query()->where('action', 'permisos.restablecidos')->exists())->toBeTrue();
});

test('the seeder configures every role without overwriting customized ones', function () {
    RolePermission::sync(UserRole::Sales, ['dashboard.view']);

    $this->seed(RolePermissionSeeder::class);

    expect(RolePermission::map()['comercial'])->toBe(['dashboard.view'])
        ->and(RolePermission::map()['operaciones'])->toEqualCanonicalizing(
            array_map(fn (Permission $p) => $p->value, RoleDefaults::for(UserRole::Operations)),
        )
        ->and(RolePermission::query()->where('permission', RolePermission::MARKER)->count())->toBe(count(UserRole::cases()));
});
