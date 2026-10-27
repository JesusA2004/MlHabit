<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('the activity log requires activity.view', function () {
    $this->get('/admin/actividad')->assertRedirect('/login');
    $this->actingAs(F::user(UserRole::Sales))->get('/admin/actividad')->assertForbidden();
    $this->actingAs(F::user(UserRole::Direction))->get('/admin/actividad')->assertOk();
});

test('entries show readable labels, entity names and a before/after diff', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $target = F::user(UserRole::Sales, ['name' => 'Carla Comercial']);

    $this->actingAs($admin);
    app(ActivityLogger::class)->log('usuario.actualizado', $target, [
        'before' => ['role' => 'comercial', 'is_active' => true],
        'after' => ['role' => 'operaciones', 'is_active' => false],
    ]);
    app(ActivityLogger::class)->log('cotizacion.aceptada');
    app(ActivityLogger::class)->log('modulo_nuevo.algo_paso');

    $this->get('/admin/actividad')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/actividad/Index')
            ->has('logs.data', 3)
            ->where('logs.data.2.actionLabel', 'Usuario actualizado')
            ->where('logs.data.2.subjectLabel', 'Usuario')
            ->where('logs.data.2.subjectReference', 'Carla Comercial')
            ->where('logs.data.2.diff.0.label', 'Rol')
            ->where('logs.data.2.diff.0.before', 'Comercial')
            ->where('logs.data.2.diff.0.after', 'Operaciones')
            ->where('logs.data.2.diff.1.after', 'No')
            ->where('logs.data.1.actionLabel', 'Cotización aceptada')
            ->where('logs.data.1.moduleLabel', 'Cotizaciones')
            // Prefijo desconocido: se humaniza sin romper.
            ->where('logs.data.0.actionLabel', 'Modulo nuevo algo paso'));
});

test('secrets never reach the page', function () {
    $admin = F::user(UserRole::SuperAdmin);
    ActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'almacenamiento.configurado',
        'changes' => [
            'host' => 'nas.local',
            'password' => 'super-secreta',
            'api_token' => 'tok_123',
            'before' => ['private_key' => 'xyz', 'host' => 'a'],
            'after' => ['private_key' => 'abc', 'host' => 'b'],
        ],
        'result' => 'success',
    ]);

    $response = $this->actingAs($admin)->get('/admin/actividad');

    $response->assertOk();
    $content = json_encode($response->inertiaProps('logs'));
    expect($content)->not->toContain('super-secreta')
        ->not->toContain('tok_123')
        ->not->toContain('xyz')
        ->toContain('nas.local');
});

test('activity can be filtered by user, module, entity and date range', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $other = F::user(UserRole::Finance);

    $this->actingAs($admin);
    app(ActivityLogger::class)->log('usuario.invitado', $other);
    $this->actingAs($other);
    app(ActivityLogger::class)->log('pago.confirmado');
    $old = app(ActivityLogger::class)->log('pago.reembolsado');
    $old->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->actingAs($admin)
        ->get('/admin/actividad?modulo=pago')
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2));

    $this->actingAs($admin)
        ->get("/admin/actividad?usuario={$admin->id}")
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'usuario.invitado'));

    $this->actingAs($admin)
        ->get('/admin/actividad?entidad='.urlencode(User::class))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));

    $this->actingAs($admin)
        ->get('/admin/actividad?modulo=pago&desde='.now()->subDays(2)->toDateString().'&hasta='.now()->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'pago.confirmado'));

    $this->actingAs($admin)
        ->get('/admin/actividad')
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.modules', fn ($modules) => collect($modules)->pluck('value')->sort()->values()->all() === ['pago', 'usuario'])
            ->where('logs.hasMore', false));
});
