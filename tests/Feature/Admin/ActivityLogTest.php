<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\User;

function activityAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('guests cannot view the activity log', function () {
    $this->get('/admin/actividad')->assertRedirect('/login');
});

test('an editor cannot view the activity log', function () {
    $editor = User::factory()->create(['role' => UserRole::Editor]);

    $this->actingAs($editor)->get('/admin/actividad')->assertForbidden();
});

test('creating a category produces a readable activity entry', function () {
    $admin = activityAdmin();

    $this->actingAs($admin)->post('/admin/categorias', [
        'name' => 'Finanzas',
        'color' => '#C7AA7D',
        'status' => 'active',
    ])->assertRedirect();

    $response = $this->actingAs($admin)->get('/admin/actividad');

    $response->assertInertia(fn ($page) => $page
        ->component('admin/actividad/Index')
        ->where('logs.data.0.actionLabel', 'Categoría creada')
        ->where('logs.data.0.userName', $admin->name)
    );
});

test('the activity log never leaks a stored password into its summary', function () {
    $admin = activityAdmin();
    $category = Category::factory()->create();

    ActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'almacenamiento.configurado',
        'subject_type' => Category::class,
        'subject_id' => $category->id,
        'changes' => ['host' => 'nas.tailnet.ts.net', 'password' => 'super-secreto'],
        'result' => 'success',
    ]);

    $response = $this->actingAs($admin)->get('/admin/actividad');

    $response->assertInertia(function ($page) {
        $raw = json_encode($page->toArray());
        expect($raw)->not->toContain('super-secreto');
        expect($raw)->toContain('nas.tailnet.ts.net');
    });
});

test('activity can be filtered by action', function () {
    $admin = activityAdmin();
    $this->actingAs($admin)->post('/admin/categorias', ['name' => 'Hábitos', 'color' => '#C7AA7D', 'status' => 'active']);
    $this->actingAs($admin)->post('/admin/redes-sociales', ['platform' => 'instagram', 'url' => 'https://instagram.com/mrinsight', 'is_visible' => true, 'order' => 0]);

    $response = $this->actingAs($admin)->get('/admin/actividad?accion=categoria.creada');

    $response->assertInertia(fn ($page) => $page
        ->where('logs.total', 1)
        ->where('logs.data.0.actionKey', 'categoria.creada')
    );
});
