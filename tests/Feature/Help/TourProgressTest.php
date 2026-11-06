<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserTourState;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function tourUser(UserRole $role): User
{
    return User::factory()->role($role)->create();
}

test('guests cannot read or write tour progress', function () {
    $this->getJson('/recorridos')->assertUnauthorized();
    $this->putJson('/recorridos/admin.general', ['status' => 'completed'])->assertUnauthorized();
    $this->deleteJson('/recorridos')->assertUnauthorized();
});

test('a new user has no tour progress yet', function () {
    $this->actingAs(tourUser(UserRole::SuperAdmin))
        ->getJson('/recorridos')
        ->assertOk()
        ->assertExactJson(['tours' => []]);
});

test('staff, customers and technicians can store their own progress', function (UserRole $role, string $tour) {
    $user = tourUser($role);

    $this->actingAs($user)
        ->putJson("/recorridos/{$tour}", ['status' => 'completed', 'step' => 7])
        ->assertOk()
        ->assertJson(['tours' => [$tour => ['status' => 'completed', 'step' => 7]]]);

    expect(UserTourState::query()->where('user_id', $user->id)->where('tour', $tour)->value('status'))
        ->toBe('completed');
})->with([
    'admin' => [UserRole::Sales, 'admin.general'],
    'cliente' => [UserRole::Customer, 'portal.general'],
    'técnico' => [UserRole::Technician, 'tecnico.general'],
]);

test('updating the same tour replaces the previous state', function () {
    $user = tourUser(UserRole::Operations);

    $this->actingAs($user)->putJson('/recorridos/admin.general', ['status' => 'dismissed'])->assertOk();
    $this->actingAs($user)->putJson('/recorridos/admin.general', ['status' => 'completed', 'step' => 0])
        ->assertOk()
        ->assertJson(['tours' => ['admin.general' => ['status' => 'completed', 'step' => 0]]]);

    expect(UserTourState::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(UserTourState::query()->where('user_id', $user->id)->value('last_step'))->toBe(0);
});

test('each person only sees their own progress', function () {
    $alice = tourUser(UserRole::Sales);
    $bob = tourUser(UserRole::Design);

    $this->actingAs($alice)->putJson('/recorridos/admin.prospectos', ['status' => 'completed'])->assertOk();

    $this->actingAs($bob)
        ->getJson('/recorridos')
        ->assertOk()
        ->assertExactJson(['tours' => []]);
});

test('invalid statuses and tour keys are rejected', function () {
    $user = tourUser(UserRole::SuperAdmin);

    $this->actingAs($user)
        ->putJson('/recorridos/admin.general', ['status' => 'aprobado'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->actingAs($user)
        ->putJson('/recorridos/admin.general', ['status' => 'completed', 'step' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('step');

    $this->actingAs($user)
        ->putJson('/recorridos/Admin_General', ['status' => 'completed'])
        ->assertNotFound();

    expect(UserTourState::query()->count())->toBe(0);
});

test('resetting forgets only the current user progress', function () {
    $alice = tourUser(UserRole::Sales);
    $bob = tourUser(UserRole::Sales);

    $this->actingAs($alice)->putJson('/recorridos/admin.general', ['status' => 'completed'])->assertOk();
    $this->actingAs($bob)->putJson('/recorridos/admin.general', ['status' => 'completed'])->assertOk();

    $this->actingAs($alice)->deleteJson('/recorridos')->assertOk()->assertExactJson(['tours' => []]);

    expect(UserTourState::query()->where('user_id', $alice->id)->count())->toBe(0)
        ->and(UserTourState::query()->where('user_id', $bob->id)->count())->toBe(1);
});

test('the admin help center is only for staff', function () {
    $this->get('/admin/ayuda')->assertRedirect('/login');
    $this->actingAs(tourUser(UserRole::Customer))->get('/admin/ayuda')->assertForbidden();
    $this->actingAs(tourUser(UserRole::Technician))->get('/admin/ayuda')->assertForbidden();

    $this->actingAs(tourUser(UserRole::Quality))
        ->get('/admin/ayuda')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/ayuda/Index', false));
});
