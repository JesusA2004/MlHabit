<?php

use App\Models\ProductCategory;
use App\Models\SitePage;
use App\Services\Web\Navigation;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

function menuItem(array $data = []): array
{
    return ['id' => 'item-'.fake()->unique()->numberBetween(1, 99999), 'label' => 'Enlace', 'type' => 'builtin', 'target' => 'preguntas', 'newTab' => false, 'visible' => true, 'children' => [], ...$data];
}

$cta = ['visible' => true, 'label' => 'Agenda tu visita', 'type' => 'builtin', 'target' => 'agendar-visita', 'newTab' => false];

it('shows the complete default menu until the owner edits it, even without published content', function () {
    WebFixtures::sections();

    // Sin catálogo ni inspiración publicados: Catálogo e Inspiración siguen
    // en el menú (sus páginas muestran un estado vacío propio).
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->where('site.navigation', fn ($links) => collect($links)->pluck('label')->all() === ['Inicio', 'Catálogo', 'Inspiración', 'Cómo funciona', 'Servicios', 'Preguntas frecuentes'])
        ->where('site.navigation', fn ($links) => collect($links)->pluck('href')->all() === ['/', '/catalogo', '/inspiracion', '/#como-funciona', null, '/preguntas-frecuentes'])
        ->where('site.navigation.4.children', fn ($children) => collect($children)->map(fn ($c) => [$c['label'], $c['href']])->all() === [
            ['Asesoría por videollamada', '/solicitar?servicio=asesoria'],
            ['Visita a domicilio', '/agendar-visita'],
            ['Cotiza tu proyecto', '/solicitar'],
        ])
        ->where('site.cta.href', '/solicitar?servicio=asesoria')
        ->where('site.cta.label', 'Agenda tu asesoría'));

    // Con contenido publicado el menú es el mismo.
    ProductCategory::factory()->create(['is_published' => true]);
    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('site.navigation.1.href', '/catalogo')
        ->where('site.navigation.2.href', '/inspiracion'));

    // Las páginas de Catálogo e Inspiración responden aun vacías.
    $this->get('/inspiracion')->assertOk();
});

it('offers every builtin target to the menu editor and accepts the new ones in a stored menu', function () use ($cta) {
    expect(collect(Navigation::builtinOptions())->pluck('value')->all())
        ->toContain('inicio', 'catalogo', 'inspiracion', 'como-funciona', 'preguntas', 'asesoria', 'agendar-visita', 'solicitar');

    // Un menú guardado antes del cambio puede sumar las nuevas secciones.
    $this->actingAs(WebFixtures::staff(['content.manage', 'content.publish']))
        ->put(route('admin.sitio.menu.update'), [
            'items' => [
                menuItem(['id' => 'inicio', 'label' => 'Inicio', 'target' => 'inicio']),
                menuItem(['id' => 'asesoria', 'label' => 'Asesoría', 'target' => 'asesoria']),
            ],
            'cta' => $cta,
        ])
        ->assertSessionHasNoErrors();

    auth()->logout();
    $this->get('/preguntas-frecuentes')->assertInertia(fn ($page) => $page
        ->where('site.navigation.0.href', '/')
        ->where('site.navigation.1.href', '/solicitar?servicio=asesoria'));
});

it('saves an ordered menu with a dropdown, a custom page and an external link', function () use ($cta) {
    WebFixtures::sections();
    $published = SitePage::factory()->published()->create(['slug' => 'nosotros', 'title' => 'Nosotros']);
    $draft = SitePage::factory()->create(['slug' => 'borrador']);

    $this->actingAs(WebFixtures::staff(['content.manage', 'content.publish']))
        ->put(route('admin.sitio.menu.update'), [
            'items' => [
                menuItem(['id' => 'nosotros', 'label' => 'Nosotros', 'type' => 'page', 'target' => (string) $published->id]),
                menuItem(['id' => 'servicios', 'label' => 'Servicios', 'type' => 'group', 'target' => '', 'children' => [
                    menuItem(['id' => 'faq', 'label' => 'Preguntas', 'target' => 'preguntas']),
                    menuItem(['id' => 'borrador', 'label' => 'Sin publicar', 'type' => 'page', 'target' => (string) $draft->id]),
                    menuItem(['id' => 'ig', 'label' => 'Instagram', 'type' => 'url', 'target' => 'https://instagram.com/mlhabitat', 'newTab' => true]),
                ]]),
                menuItem(['id' => 'oculto', 'label' => 'Oculto', 'visible' => false]),
            ],
            'cta' => $cta,
        ])
        ->assertRedirect()->assertSessionHasNoErrors();

    auth()->logout();
    $this->get('/preguntas-frecuentes')->assertOk()->assertInertia(fn ($page) => $page
        ->has('site.navigation', 2)
        ->where('site.navigation.0.href', '/nosotros')
        ->where('site.navigation.1.label', 'Servicios')
        ->where('site.navigation.1.href', null)
        // La página sin publicar no aparece.
        ->has('site.navigation.1.children', 2)
        ->where('site.navigation.1.children.1.href', 'https://instagram.com/mlhabitat')
        ->where('site.navigation.1.children.1.external', true)
        ->where('site.navigation.1.children.1.newTab', true)
        ->where('site.cta.href', '/agendar-visita')
        ->where('site.cta.label', 'Agenda tu visita'));

    // Al despublicar la página, su enlace desaparece del menú (caché invalidada).
    $published->forceFill(['is_published' => false])->save();
    $this->get('/preguntas-frecuentes')->assertInertia(fn ($page) => $page->has('site.navigation', 1));
});

it('rejects unsafe links, unknown targets and nested dropdowns', function (array $item, string $error) use ($cta) {
    $this->actingAs(WebFixtures::staff(['content.manage', 'content.publish']))
        ->put(route('admin.sitio.menu.update'), ['items' => [menuItem($item)], 'cta' => $cta])
        ->assertSessionHasErrors($error);

    expect(Navigation::stored()['customized'])->toBeFalse();
})->with([
    'javascript' => [['type' => 'url', 'target' => 'javascript:alert(1)'], 'items.0.target'],
    'http' => [['type' => 'url', 'target' => 'http://example.com'], 'items.0.target'],
    'protocol-relative' => [['type' => 'url', 'target' => '//evil.com'], 'items.0.target'],
    'builtin desconocido' => [['type' => 'builtin', 'target' => 'nada'], 'items.0.target'],
    'página inexistente' => [['type' => 'page', 'target' => '999999'], 'items.0.target'],
    'sin texto' => [['label' => '  '], 'items.0.label'],
    'texto largo' => [['label' => str_repeat('a', 41)], 'items.0.label'],
    'grupo vacío' => [['type' => 'group', 'target' => ''], 'items.0.children'],
    'submenú anidado' => [['type' => 'group', 'target' => '', 'children' => [menuItem(['type' => 'group', 'target' => '', 'children' => [menuItem()]])]], 'items.0.children.0.type'],
]);

it('lets the owner hide the highlighted button and reset the menu', function () use ($cta) {
    $user = WebFixtures::staff(['content.manage', 'content.publish']);

    $this->actingAs($user)->put(route('admin.sitio.menu.update'), [
        'items' => [menuItem()],
        'cta' => [...$cta, 'visible' => false],
    ])->assertSessionHasNoErrors();

    $this->get('/preguntas-frecuentes')->assertInertia(fn ($page) => $page->where('site.cta', null));

    $this->actingAs($user)->post(route('admin.sitio.menu.restablecer'))->assertRedirect();
    expect(Navigation::stored()['customized'])->toBeFalse();
    $this->get('/preguntas-frecuentes')->assertInertia(fn ($page) => $page
        ->where('site.cta.href', '/solicitar?servicio=asesoria')
        ->has('site.navigation', 6));
});

it('requires content.publish to change the menu', function () use ($cta) {
    $this->actingAs(WebFixtures::staff(['content.manage']))
        ->put(route('admin.sitio.menu.update'), ['items' => [menuItem()], 'cta' => $cta])
        ->assertForbidden();
});
