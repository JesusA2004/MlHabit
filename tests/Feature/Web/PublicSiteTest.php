<?php

use App\Enums\UserRole;
use App\Models\Faq;
use App\Models\Page;
use App\Models\ShowcaseProject;
use App\Models\Testimonial;
use App\Services\Web\PublicSite;
use App\Support\Html;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the home with CMS sections in order and hides empty live sections', function () {
    WebFixtures::sections();

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('public/Home')
        ->where('sections.0.type', 'hero')
        ->where('overlay', true)
        ->has('site.name')
        ->where('seo.canonical', rtrim(url('/'), '/').'/')
        ->where('sections', fn ($sections) => collect($sections)->pluck('type')->doesntContain('testimonials')
            && collect($sections)->pluck('type')->doesntContain('categories')
            && collect($sections)->pluck('type')->contains('process')));
});

it('shows the visit price only when there is an active visit policy', function () {
    WebFixtures::sections();

    $this->get('/')->assertInertia(fn ($page) => $page->where('sections', fn ($s) => collect($s)->firstWhere('type', 'home_visit')['visit'] === null));

    DB::table('visit_policies')->insert([
        'version' => 1, 'fee_amount' => '650.00', 'currency' => 'MXN', 'is_creditable' => true,
        'scope' => 'Medición', 'cancellation_policy' => '—', 'reschedule_policy' => '—', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    PublicSite::flush();

    $this->get('/')->assertInertia(fn ($page) => $page->where('sections', fn ($s) => collect($s)->firstWhere('type', 'home_visit')['visit']['fee'] === '650.00'
        && collect($s)->firstWhere('type', 'home_visit')['visit']['isCreditable'] === true));
});

it('never publishes a testimonial without consent', function () {
    expect(fn () => Testimonial::factory()->create(['is_published' => true]))->toThrow(ValidationException::class);

    $this->actingAs(WebFixtures::admin())
        ->post(route('admin.sitio.testimonios.store'), ['author_name' => 'Ana', 'quote' => 'Muy buen trabajo.', 'is_published' => true])
        ->assertSessionHasErrors('consent_obtained_at');

    expect(Testimonial::query()->count())->toBe(0);
});

it('requires content.publish to publish testimonials', function () {
    $this->actingAs(WebFixtures::staff(['content.manage']))
        ->post(route('admin.sitio.testimonios.store'), ['author_name' => 'Ana', 'quote' => 'Gracias.', 'consent_obtained_at' => now()->toDateString(), 'is_published' => true])
        ->assertForbidden();
});

it('shows only published testimonials with consent on the home', function () {
    WebFixtures::sections();
    Testimonial::factory()->published()->create(['author_name' => 'Laura']);
    Testimonial::factory()->withConsent()->create(['author_name' => 'Borrador']);

    $this->get('/')->assertInertia(fn ($page) => $page->where('sections', function ($s) {
        $section = collect($s)->firstWhere('type', 'testimonials');

        return $section !== null && count($section['items']) === 1 && $section['items'][0]['authorName'] === 'Laura';
    }));
});

it('labels inspiration projects by origin and hides drafts', function () {
    $real = ShowcaseProject::factory()->realPhoto()->create(['slug' => 'cocina-real']);
    ShowcaseProject::factory()->draft()->create(['slug' => 'borrador']);

    $this->get('/inspiracion')->assertOk()->assertInertia(fn ($page) => $page->component('public/inspiracion/Index')
        ->has('projects.data', 1)->where('projects.data.0.originLabel', 'Proyecto real'));
    $this->get('/inspiracion/cocina-real')->assertOk()->assertInertia(fn ($page) => $page->component('public/inspiracion/Show')
        ->where('project.origin', 'real_photo'));
    $this->get('/inspiracion/borrador')->assertNotFound();
    expect($real->exists)->toBeTrue();
});

it('renders FAQ with FAQPage structured data', function () {
    Faq::factory()->create(['question' => '¿Cuánto tarda la instalación?']);
    Faq::factory()->create(['question' => 'Oculta', 'is_published' => false]);

    $this->get('/preguntas-frecuentes')->assertOk()->assertInertia(fn ($page) => $page->component('public/preguntas/Index')
        ->has('groups', 1)->has('groups.0.items', 1)
        ->where('seo.jsonLd', fn ($json) => str_contains((string) $json, '"FAQPage"')));
});

it('serves published legal pages and 404s drafts', function () {
    Page::factory()->create(['slug' => 'aviso-de-privacidad', 'title' => 'Aviso de privacidad']);
    Page::factory()->draft()->create(['slug' => 'terminos-y-condiciones']);

    $this->get('/legal/aviso-de-privacidad')->assertOk()->assertInertia(fn ($page) => $page->component('public/legal/Show'));
    $this->get('/aviso-de-privacidad')->assertRedirect('/legal/aviso-de-privacidad');
    $this->get('/legal/terminos-y-condiciones')->assertNotFound();
});

it('increments the page version on each publish', function () {
    $admin = WebFixtures::admin();
    $page = Page::factory()->draft()->create();

    $this->actingAs($admin)->post(route('admin.sitio.paginas.publicar', $page))->assertRedirect();
    expect($page->refresh()->version)->toBe(1)->and($page->is_published)->toBeTrue();

    $this->actingAs($admin)->put(route('admin.sitio.paginas.update', $page), ['title' => $page->title, 'body' => '<p>Texto nuevo</p>'])->assertRedirect();
    expect($page->refresh()->version)->toBe(2);

    $this->actingAs(WebFixtures::staff(['content.manage'], UserRole::Quality))
        ->put(route('admin.sitio.paginas.update', $page), ['title' => 'Otro', 'body' => '<p>x</p>'])
        ->assertForbidden();
});

it('sanitizes rich HTML against XSS', function () {
    expect(Html::clean('<p>Hola</p><script>alert(1)</script>'))->toBe('<p>Hola</p>')
        ->and(Html::clean('<img src="https://x.test/a.jpg" onerror="alert(1)">'))->not->toContain('onerror')
        ->and(Html::clean('<a href="javascript:alert(1)">x</a>'))->not->toContain('javascript')
        ->and(Html::clean('<p onclick="x()" style="color:red">Hi</p>'))->toBe('<p>Hi</p>');

    $page = Page::factory()->create(['body' => '<h2>Datos</h2><iframe src="https://evil"></iframe><a href="javascript:void(0)">x</a>']);
    expect($page->refresh()->body)->not->toContain('iframe')->not->toContain('javascript');
});

it('exposes a sitemap with published content only and a robots file', function () {
    Page::factory()->create(['slug' => 'aviso-de-privacidad']);
    Page::factory()->draft()->create(['slug' => 'pendiente']);
    ShowcaseProject::factory()->create(['slug' => 'publico']);
    ShowcaseProject::factory()->draft()->create(['slug' => 'secreto']);

    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/legal/aviso-de-privacidad', false)
        ->assertSee('/inspiracion/publico', false)
        ->assertDontSee('pendiente', false)
        ->assertDontSee('secreto', false);

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')->assertSee('Disallow: /portal')->assertSee('Disallow: /tecnico')->assertSee('Disallow: /archivos')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));
});

it('tracks anonymous public visits but not the panel, staff or bots', function () {
    WebFixtures::sections();
    $browser = ['User-Agent' => 'Mozilla/5.0 (iPhone) Safari'];

    $this->withHeaders($browser)->get('/')->assertOk();
    $this->withHeaders($browser)->get('/preguntas-frecuentes')->assertOk();
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/')->assertOk();
    $this->actingAs(WebFixtures::admin())->withHeaders($browser)->get('/')->assertOk();

    $this->assertDatabaseCount('page_views', 2);
    $this->assertDatabaseHas('page_views', ['path' => '/', 'label' => 'Inicio', 'is_unique' => true]);
    $this->assertDatabaseHas('page_views', ['path' => '/preguntas-frecuentes', 'label' => 'Preguntas frecuentes']);

    $this->actingAs(WebFixtures::admin())->get(route('admin.estadisticas'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/estadisticas/Index')->where('summary.totalViews', 2)->has('areas'));
});
