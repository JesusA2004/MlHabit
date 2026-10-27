<?php

use App\Enums\UserRole;
use App\Models\InstallationOrder;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

/**
 * Matriz de acceso a las pantallas clave de operaciones (GET).
 * true = 200, false = 403.
 */
dataset('screens', [
    'proveedores' => ['admin.proveedores.index', [UserRole::Operations, UserRole::Quality, UserRole::Finance], [UserRole::Sales, UserRole::Design, UserRole::Credit]],
    'tecnicos' => ['admin.tecnicos.index', [UserRole::Operations, UserRole::Quality], [UserRole::Sales, UserRole::Credit]],
    'tarifarios' => ['admin.tarifarios.index', [UserRole::Operations, UserRole::Finance], [UserRole::Sales, UserRole::Quality]],
    'fabricacion' => ['admin.fabricacion.index', [UserRole::Operations, UserRole::Design], [UserRole::Sales, UserRole::Finance]],
    'instalaciones' => ['admin.instalaciones.index', [UserRole::Operations, UserRole::Quality], [UserRole::Sales, UserRole::Finance]],
    'calidad' => ['admin.calidad.index', [UserRole::Quality, UserRole::Operations], [UserRole::Sales, UserRole::Finance]],
    'pagos' => ['admin.pagos-proveedores.index', [UserRole::Finance, UserRole::Operations], [UserRole::Sales, UserRole::Quality]],
    'incidencias' => ['admin.incidencias.index', [UserRole::Operations, UserRole::Quality], [UserRole::Sales, UserRole::Finance]],
    'garantias' => ['admin.garantias.index', [UserRole::Quality, UserRole::Operations], [UserRole::Sales, UserRole::Finance]],
]);

it('enforces the role matrix on operations screens', function (string $route, array $allowed, array $denied) {
    foreach ($allowed as $role) {
        $this->actingAs(OpsSetup::staff($role))->get(route($route))->assertOk();
    }
    foreach ($denied as $role) {
        $this->actingAs(OpsSetup::staff($role))->get(route($route))->assertForbidden();
    }
})->with('screens');

it('renders the existing Inertia page for each operations screen', function () {
    $admin = OpsSetup::staff(UserRole::SuperAdmin);
    $screens = [
        'admin.proveedores.index' => 'admin/proveedores/Index',
        'admin.tecnicos.index' => 'admin/tecnicos/Index',
        'admin.tarifarios.index' => 'admin/tarifarios/Index',
        'admin.fabricacion.index' => 'admin/fabricacion/Index',
        'admin.instalaciones.index' => 'admin/instalaciones/Index',
        'admin.calidad.index' => 'admin/calidad/Index',
        'admin.pagos-proveedores.index' => 'admin/pagos-proveedores/Index',
        'admin.incidencias.index' => 'admin/incidencias/Index',
        'admin.garantias.index' => 'admin/garantias/Index',
    ];

    foreach ($screens as $route => $component) {
        $this->actingAs($admin)->get(route($route))->assertInertia(fn ($page) => $page->component($component));
    }

    $order = OpsSetup::order();
    $this->actingAs($admin)->get(route('admin.instalaciones.show', $order))
        ->assertInertia(fn ($page) => $page->component('admin/instalaciones/Show')->has('order.items', 2));
});

it('keeps technicians and clients out of the admin panel', function () {
    $this->actingAs(OpsSetup::installer())->get(route('admin.instalaciones.index'))->assertForbidden();
    $this->actingAs(OperationsFixtures::client())->get(route('admin.incidencias.index'))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('admin.proveedores.index'))->assertRedirect(route('login'));
    $this->get(route('tecnico.instalaciones.index'))->assertRedirect(route('login'));
    $this->get(route('portal.incidencias.create'))->assertRedirect(route('login'));
});

it('denies mutating endpoints without the manage permission', function () {
    $order = OpsSetup::order();
    $item = $order->items()->first();
    $finance = OpsSetup::staff(UserRole::Finance);

    $this->actingAs($finance)->post(route('admin.instalaciones.programar', $order), ['date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00'])->assertForbidden();
    $this->actingAs($finance)->post(route('admin.instalaciones.extras.aprobar', [$order, $item]))->assertForbidden();
    $this->actingAs($finance)->post(route('admin.instalaciones.cancelar', $order), ['reason' => 'x'])->assertForbidden();
    $this->actingAs(OpsSetup::staff(UserRole::Quality))->post(route('admin.instalaciones.store'), [])->assertForbidden();

    expect(InstallationOrder::query()->sole()->status->value)->toBe('draft');
});

it('lets the payout and quality staff view installation detail but not the sales team', function () {
    $order = OpsSetup::order();

    $this->actingAs(OpsSetup::staff(UserRole::Finance))->get(route('admin.instalaciones.show', $order))->assertOk();
    $this->actingAs(OpsSetup::staff(UserRole::Quality))->get(route('admin.calidad.show', $order))->assertOk();
    $this->actingAs(OpsSetup::staff(UserRole::Sales))->get(route('admin.instalaciones.show', $order))->assertForbidden();
});
