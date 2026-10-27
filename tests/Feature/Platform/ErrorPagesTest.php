<?php

use App\Enums\UserRole;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Platform\PlatformFixtures as F;

beforeEach(function () {
    $this->withoutVite();
});

test('unknown pages render the branded 404 component', function () {
    $this->get('/esta-pagina-no-existe-'.uniqid())
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/Error')
            ->where('status', 404)
            ->where('message', null));
});

test('customers and technicians get the branded 403 in the admin area', function () {
    foreach ([UserRole::Customer, UserRole::Technician] as $role) {
        $this->actingAs(F::user($role))
            ->get('/admin')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/Error')
                ->where('status', 403)
                ->where('message', 'Esta área es solo para el equipo de ML Habitat.'));
    }
});

test('json requests keep json errors', function () {
    $this->getJson('/esta-tampoco-existe')->assertNotFound()->assertJsonStructure(['message']);
});

test('expired sessions show a friendly page or go back on Inertia visits', function () {
    Route::middleware('web')->get('/__platform-expired', fn () => throw new TokenMismatchException);

    $this->get('/__platform-expired')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page->component('errors/Error')->where('status', 419));

    $this->from('/settings/profile')
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion(), 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/__platform-expired')
        ->assertRedirect('/settings/profile')
        ->assertSessionHas('error', 'Tu sesión expiró. Vuelve a intentarlo.');
});

test('500 is branded only when debug is off', function () {
    Route::get('/__platform-boom', fn () => throw new RuntimeException('detalle interno'));

    config(['app.debug' => false]);
    $response = $this->get('/__platform-boom');
    $response->assertStatus(500)->assertInertia(fn (Assert $page) => $page->component('errors/Error')->where('status', 500));
    expect($response->getContent())->not->toContain('detalle interno');

    config(['app.debug' => true]);
    $response = $this->get('/__platform-boom');
    $response->assertStatus(500);
    expect($response->original)->not->toBeInstanceOf(View::class);
});

test('rate limited and maintenance errors are branded', function () {
    Route::get('/__platform-429', fn () => abort(429));
    Route::get('/__platform-503', fn () => abort(503));

    $this->get('/__platform-429')->assertStatus(429)->assertInertia(fn (Assert $page) => $page->where('status', 429));
    $this->get('/__platform-503')->assertStatus(503)->assertInertia(fn (Assert $page) => $page->where('status', 503));
});

test('the static maintenance fallback view exists and is branded', function () {
    $html = view('errors.503')->render();

    expect($html)->toContain('ML Habitat')->toContain('mantenimiento');
});
