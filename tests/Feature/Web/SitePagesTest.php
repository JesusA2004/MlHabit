<?php

use App\Cms\Layout\Blocks;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\PageTemplates;
use App\Cms\Layout\VideoEmbed;
use App\Models\Media;
use App\Models\SitePage;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Services\Web\SiteBuilder;
use App\Services\Web\SitePages;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
    SitePages::forgetReserved();
});

/** Crea una página desde el panel y la devuelve. */
function createSitePage(object $test, array $data = []): SitePage
{
    $test->post(route('admin.sitio.paginas-web.store'), [
        'title' => 'Cocinas integrales',
        'template' => 'service',
        ...$data,
    ])->assertRedirect();

    return SitePage::query()->latest('id')->firstOrFail();
}

// ---------------------------------------------------------------------
// Plantillas y bloques nuevos
// ---------------------------------------------------------------------

it('builds every page template from valid blocks with a single H1', function (string $id) {
    $clean = LayoutValidator::snapshot(['sections' => PageTemplates::sections($id, 'Clósets a la medida')]);

    expect($clean['sections'])->not->toBeEmpty()
        ->and(LayoutValidator::h1Count($clean['sections']))->toBe(1);
})->with(PageTemplates::ids());

it('accepts only YouTube and Vimeo links for embedded videos', function () {
    expect(VideoEmbed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'])
        ->and(VideoEmbed::parse('https://youtu.be/dQw4w9WgXcQ?t=10'))->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'])
        ->and(VideoEmbed::parse('https://www.youtube.com/shorts/dQw4w9WgXcQ'))->toBe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'])
        ->and(VideoEmbed::parse('https://vimeo.com/76979871'))->toBe(['provider' => 'vimeo', 'id' => '76979871'])
        ->and(VideoEmbed::parse('https://player.vimeo.com/video/76979871'))->toBe(['provider' => 'vimeo', 'id' => '76979871'])
        ->and(VideoEmbed::parse('https://evil.example.com/watch?v=dQw4w9WgXcQ'))->toBeNull()
        ->and(VideoEmbed::parse('https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ'))->toBeNull()
        ->and(VideoEmbed::parse('javascript:alert(1)'))->toBeNull();

    $ok = LayoutValidator::layout(Blocks::layout([], [Blocks::make('embed', 0, 0, 8, 18, ['url' => 'https://youtu.be/dQw4w9WgXcQ'])]));
    expect($ok['blocks'][0]['props'])->toMatchArray(['provider' => 'youtube', 'videoId' => 'dQw4w9WgXcQ']);

    expect(fn () => LayoutValidator::layout(Blocks::layout([], [Blocks::make('embed', 0, 0, 8, 18, ['url' => 'https://example.com/video.mp4'])])))
        ->toThrow(ValidationException::class);
});

it('keeps only public images in a gallery and reports them as used media', function () {
    $public = Media::factory()->create();
    $private = Media::factory()->private()->create();

    $layout = Blocks::layout([], [Blocks::make('gallery', 0, 0, 12, 20, ['items' => [['mediaId' => $public->id, 'alt' => 'Cocina en roble']]])]);
    $clean = LayoutValidator::layout($layout);

    expect($clean['blocks'][0]['props']['items'])->toBe([['mediaId' => $public->id, 'alt' => 'Cocina en roble', 'caption' => '']])
        ->and(LayoutValidator::mediaIds($clean))->toBe([$public->id]);

    $bad = Blocks::layout([], [Blocks::make('gallery', 0, 0, 12, 20, ['items' => [['mediaId' => $private->id]]])]);
    expect(fn () => LayoutValidator::layout($bad))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------
// Alta, edición, duplicado, borrado
// ---------------------------------------------------------------------

it('creates a page from a template as an unpublished draft and opens the editor', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));

    $response = $this->post(route('admin.sitio.paginas-web.store'), ['title' => 'Clósets a la medida', 'template' => 'service']);
    $page = SitePage::query()->firstOrFail();

    $response->assertRedirect(route('admin.sitio.paginas-web.editor.edit', $page));
    expect($page->slug)->toBe('closets-a-la-medida')
        ->and($page->is_published)->toBeFalse();

    $draft = SitePageDraft::query()->where('page', $page->pageKey())->firstOrFail();
    expect(count($draft->snapshot['sections']))->toBe(5);

    $this->get(route('admin.sitio.paginas-web.editor.edit', $page))->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/sitio/Editor')
            ->where('page.kind', 'page')
            ->where('page.id', $page->id)
            ->has('sections', 5));

    // La portada no se toca.
    expect(SiteSection::query()->where('page', '!=', 'home')->count())->toBe(0);
});

it('rejects slugs that belong to the site or to another page', function (string $slug) {
    $this->actingAs(WebFixtures::staff(['content.manage']));

    $this->post(route('admin.sitio.paginas-web.store'), ['title' => 'Prueba', 'slug' => $slug, 'template' => 'blank'])
        ->assertSessionHasErrors('slug');
})->with(['catalogo', 'admin', 'solicitar', 'agendar-visita', 'legal', 'preguntas-frecuentes', 'build']);

it('never creates a page whose automatic slug would shadow a route', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));

    $page = createSitePage($this, ['title' => 'Catálogo', 'template' => 'blank']);

    expect($page->slug)->not->toBe('catalogo')
        ->and(SitePages::isReserved($page->slug))->toBeFalse();
});

it('updates the page data and SEO', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));
    $page = createSitePage($this);
    $og = Media::factory()->create();

    $this->put(route('admin.sitio.paginas-web.update', $page), [
        'title' => 'Cocinas',
        'slug' => 'cocinas',
        'seo_title' => 'Cocinas integrales a la medida',
        'seo_description' => 'Diseñamos y fabricamos tu cocina.',
        'og_media_id' => $og->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($page->refresh())->slug->toBe('cocinas')->seo_title->toBe('Cocinas integrales a la medida')->og_media_id->toBe($og->id);
});

it('duplicates a page with fresh section keys and deletes only unpublished pages', function () {
    $this->actingAs(WebFixtures::staff(['content.manage', 'content.publish']));
    $page = createSitePage($this);

    $this->post(route('admin.sitio.paginas-web.duplicar', $page))->assertRedirect();
    $copy = SitePage::query()->latest('id')->firstOrFail();

    expect($copy->id)->not->toBe($page->id)
        ->and($copy->slug)->toBe($page->slug.'-copia')
        ->and($copy->is_published)->toBeFalse();

    $keys = fn (SitePage $p) => array_column(app(SiteBuilder::class)->draft($p->pageKey())->snapshot['sections'], 'key');
    expect(array_intersect($keys($page), $keys($copy)))->toBe([]);

    // Publicada no se borra.
    $this->post(route('admin.sitio.paginas-web.publicar', $page))->assertRedirect();
    $this->delete(route('admin.sitio.paginas-web.destroy', $page))->assertSessionHas('error');
    expect(SitePage::query()->whereKey($page->id)->exists())->toBeTrue();

    $this->post(route('admin.sitio.paginas-web.despublicar', $page));
    $this->delete(route('admin.sitio.paginas-web.destroy', $page))->assertRedirect();

    expect(SitePage::query()->whereKey($page->id)->exists())->toBeFalse()
        ->and(SiteSection::query()->where('page', $page->pageKey())->count())->toBe(0)
        ->and(SitePageVersion::query()->where('page', $page->pageKey())->count())->toBe(0)
        ->and(SitePageDraft::query()->where('page', $page->pageKey())->count())->toBe(0);
});

it('requires content.publish to publish a page', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));
    $page = createSitePage($this);

    $this->post(route('admin.sitio.paginas-web.publicar', $page))->assertForbidden();
    $this->postJson(route('admin.sitio.paginas-web.editor.publicar', $page))->assertForbidden();
});

// ---------------------------------------------------------------------
// Editor por página y sitio público
// ---------------------------------------------------------------------

it('saves, publishes and serves a custom page at its clean URL', function () {
    $this->actingAs(WebFixtures::staff(['content.manage', 'content.publish']));
    $page = createSitePage($this, ['title' => 'Nosotros', 'template' => 'about']);

    // Sin publicar: 404 para visitantes, vista previa para el equipo.
    $this->get('/'.$page->slug)->assertOk()->assertInertia(fn ($p) => $p->component('public/SitePage')->where('preview', true));
    auth()->logout();
    $this->get('/'.$page->slug)->assertNotFound();

    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $draft = app(SiteBuilder::class)->draft($page->pageKey());
    $sections = $draft->snapshot['sections'];
    $sections[0]['layout']['blocks'][] = Blocks::make('embed', 0, 24, 8, 18, ['url' => 'https://youtu.be/dQw4w9WgXcQ']);

    $this->actingAs($user)
        ->putJson(route('admin.sitio.paginas-web.editor.borrador', $page), ['revision' => $draft->revision, 'sections' => $sections])
        ->assertOk()->assertJsonPath('hasChanges', true);

    $this->actingAs($user)
        ->postJson(route('admin.sitio.paginas-web.editor.publicar', $page), ['note' => 'Primera versión'])
        ->assertOk()->assertJsonPath('versions.0.number', 1);

    expect($page->refresh()->is_published)->toBeTrue()
        ->and(SiteSection::query()->where('page', $page->pageKey())->count())->toBe(count($sections))
        // La portada sigue con sus propias versiones.
        ->and(SitePageVersion::query()->where('page', 'home')->count())->toBe(0);

    auth()->logout();
    $this->get('/'.$page->slug)->assertOk()->assertInertia(fn ($p) => $p->component('public/SitePage')
        ->where('page.title', 'Nosotros')
        ->where('preview', false)
        ->where('seo.canonical', url('/'.$page->slug))
        // Testimonios sin publicar: su sección curada se oculta sola.
        ->has('sections', count($sections) - 1)
        ->has('site.navigation'));

    // Sitemap.
    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/'.$page->slug), false);

    // Despublicar la oculta otra vez.
    $this->actingAs($user)->post(route('admin.sitio.paginas-web.despublicar', $page));
    auth()->logout();
    $this->get('/'.$page->slug)->assertNotFound();
});

it('keeps existing routes working with the catch-all page route', function () {
    WebFixtures::sections();
    SitePage::factory()->published()->create(['slug' => 'nosotros']);

    $this->get('/')->assertOk()->assertInertia(fn ($p) => $p->component('public/Home'));
    $this->get('/preguntas-frecuentes')->assertOk()->assertInertia(fn ($p) => $p->component('public/preguntas/Index'));
    $this->get('/solicitar')->assertOk();
    $this->get('/no-existe-esta-pagina')->assertNotFound();
    $this->get('/nosotros')->assertOk()->assertInertia(fn ($p) => $p->component('public/SitePage'));
});

it('restores only versions of the same page', function () {
    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $this->actingAs($user);
    $a = createSitePage($this, ['title' => 'Página A', 'template' => 'blank']);
    $b = createSitePage($this, ['title' => 'Página B', 'template' => 'blank']);

    $this->postJson(route('admin.sitio.paginas-web.editor.publicar', $a))->assertOk();
    $version = SitePageVersion::query()->where('page', $a->pageKey())->firstOrFail();

    $this->postJson(route('admin.sitio.paginas-web.editor.versiones.restaurar', [$b, $version]))->assertNotFound();
    $this->postJson(route('admin.sitio.editor.versiones.restaurar', $version))->assertNotFound();
    $this->postJson(route('admin.sitio.paginas-web.editor.versiones.restaurar', [$a, $version]))->assertOk();
});

it('previews a custom page draft through a signed link', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));
    $page = createSitePage($this);

    $this->get(URL::temporarySignedRoute('admin.sitio.paginas-web.editor.vista-previa', now()->addHour(), ['sitePage' => $page->id]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($p) => $p->component('public/SitePage')->where('preview', true));

    $this->get(route('admin.sitio.paginas-web.editor.vista-previa', $page))->assertForbidden();
});

it('lists custom pages and the menu in the site admin', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']));
    createSitePage($this);

    $this->get(route('admin.sitio.index', ['pestana' => 'paginas-web']))->assertOk()
        ->assertInertia(fn ($p) => $p->where('tab', 'paginas-web')
            ->has('sitePages', 1)
            ->has('pageTemplates', count(PageTemplates::ids()))
            ->has('navigation.items', 4)
            ->where('navigation.customized', false));
});
