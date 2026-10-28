<?php

use App\Enums\UserRole;
use App\Models\User;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

it('shares the same site navigation with every public page, also the CRM flows', function () {
    WebFixtures::sections();

    foreach (['/', '/preguntas-frecuentes', '/solicitar'] as $url) {
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->has('site.navigation')
            ->has('site.contact')
            ->where('site.cta.href', '/solicitar'));
    }
});

it('does not send the public site data to the panel', function () {
    $this->actingAs(WebFixtures::admin())->get('/admin')->assertOk()
        ->assertInertia(fn ($page) => $page->missing('site'));
});

it('tells the public header where each role lands after signing in', function (UserRole $role, string $home) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/preguntas-frecuentes')->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.user.homePath', $home));
})->with([
    'cliente' => [UserRole::Customer, '/portal'],
    'técnico' => [UserRole::Technician, '/tecnico'],
]);
