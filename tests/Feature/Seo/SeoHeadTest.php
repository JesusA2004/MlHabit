<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Web\OgImage;
use App\Services\Web\Seo;
use App\Support\Settings;
use Tests\Feature\Platform\PlatformFixtures as F;
use Tests\Feature\Web\WebFixtures;

/*
 * Las vistas previas (WhatsApp, Facebook, LinkedIn, X, Slack…) y muchos
 * rastreadores NO ejecutan JavaScript: las etiquetas deben estar en el HTML
 * que entrega el servidor, no solo en las props de Inertia.
 */

beforeEach(function () {
    $this->withoutVite();
    // Se prueba el HTML que genera Laravel (sin SSR): debe bastar por sí solo.
    config(['inertia.ssr.enabled' => false]);
});

/** Contenido de <head> del HTML crudo. */
function rawHead(string $html): string
{
    preg_match('/<head>(.*?)<\/head>/s', $html, $m);

    return $m[1] ?? '';
}

/** @return array<string, mixed> */
function jsonLdGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $m);

    return json_decode($m[1] ?? '{}', true) ?: [];
}

it('renders Open Graph, Twitter, canonical and JSON-LD in the raw HTML of the home', function () {
    WebFixtures::sections();

    $html = $this->get('/')->assertOk()->getContent();
    $head = rawHead($html);
    $home = rtrim(url('/'), '/').'/';

    expect($head)
        ->toContain('<title data-inertia="">')
        ->toContain('| ML Habitat</title>')
        ->toContain('<meta name="description" content="')
        ->toContain('<link rel="canonical" href="'.$home.'" data-inertia="canonical">')
        ->toContain('hreflang="es-MX"')
        ->toContain('<meta property="og:type" content="website"')
        ->toContain('<meta property="og:site_name" content="ML Habitat"')
        ->toContain('<meta property="og:locale" content="es_MX"')
        ->toContain('<meta property="og:url" content="'.$home.'"')
        ->toContain('<meta property="og:title" content="')
        ->toContain('<meta property="og:description" content="')
        ->toContain('<meta property="og:image" content="'.url('/og/home/inicio.png').'?v=')
        ->toContain('<meta property="og:image:width" content="1200"')
        ->toContain('<meta property="og:image:height" content="630"')
        ->toContain('<meta property="og:image:type" content="image/png"')
        ->toContain('<meta property="og:image:alt" content="')
        ->toContain('<meta name="twitter:card" content="summary_large_image"')
        ->toContain('<meta name="theme-color" content="#F7F5F2">')
        ->toContain('<meta name="robots" content="index, follow')
        ->toContain('<link rel="manifest" href="/site.webmanifest">');

    // Cada etiqueta una sola vez (Inertia las reutiliza por data-inertia).
    expect(substr_count($head, 'property="og:title"'))->toBe(1)
        ->and(substr_count($head, 'name="description"'))->toBe(1);

    $types = collect(jsonLdGraph($html)['@graph'] ?? [])->pluck('@type')->all();
    expect($types)->toContain('Organization')->toContain('WebSite');

    // No hay buscador interno: sin SearchAction.
    expect($html)->not->toContain('SearchAction')
        ->and($html)->not->toContain('class="dark"');
});

it('renders product meta tags and Product + BreadcrumbList JSON-LD server side', function () {
    $category = ProductCategory::factory()->create(['name' => 'Cocinas integrales', 'slug' => 'cocinas-integrales', 'is_published' => true]);
    $product = Product::factory()->for($category, 'category')->create([
        'name' => 'Cocina Nórdica',
        'slug' => 'cocina-nordica',
        'summary' => 'Cocina integral en roble con isla central y cubierta de cuarzo.',
    ]);

    $url = url('/catalogo/cocinas-integrales/cocina-nordica');
    $html = $this->get($url)->assertOk()->getContent();
    $head = rawHead($html);

    expect($head)
        ->toContain('<title data-inertia="">Cocina Nórdica · Cocinas integrales | ML Habitat</title>')
        ->toContain('<meta property="og:type" content="product"')
        ->toContain('<meta property="og:title" content="Cocina Nórdica · Cocinas integrales | ML Habitat"')
        ->toContain('<link rel="canonical" href="'.$url.'"')
        ->toContain('<meta property="og:image" content="'.url('/og/product/cocina-nordica.png').'?v=')
        ->toContain('Cocina integral en roble con isla central');

    $graph = collect(jsonLdGraph($html)['@graph'] ?? []);
    $productLd = $graph->firstWhere('@type', 'Product');

    expect($productLd)->not->toBeNull()
        ->and($productLd['name'])->toBe('Cocina Nórdica')
        ->and($productLd)->not->toHaveKey('offers')
        ->and($graph->pluck('@type'))->toContain('BreadcrumbList');

    // Las props de Inertia traen las mismas etiquetas para la navegación SPA.
    $this->get($url)->assertInertia(fn ($page) => $page
        ->where('seo.head', fn ($head) => collect($head)->contains(fn ($tag) => str_contains($tag, 'data-inertia="og:image"'))));
});

it('generates a cached 1200x630 PNG preview and changes its URL when content changes', function () {
    $category = ProductCategory::factory()->create(['slug' => 'closets', 'name' => 'Clósets', 'is_published' => true]);
    $product = Product::factory()->for($category, 'category')->create(['name' => 'Clóset Lino', 'slug' => 'closet-lino']);

    $response = $this->get('/og/product/closet-lino.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    $png = file_get_contents($response->baseResponse->getFile()->getPathname());
    $size = getimagesizefromstring((string) $png);

    expect($size[0])->toBe(1200)->and($size[1])->toBe(630);

    $og = app(OgImage::class);
    $before = $og->meta($og->product($product->fresh()))['url'];
    $product->update(['name' => 'Clóset Lino a la medida']);
    $after = $og->meta($og->product($product->fresh()))['url'];

    expect($after)->not->toBe($before);

    $this->get('/og/category/closets.png')->assertOk();
    $this->get('/og/page/preguntas-frecuentes.png')->assertOk();
    $this->get('/og/home/inicio.png')->assertOk();
});

it('does not render previews for drafts or unknown content', function () {
    $category = ProductCategory::factory()->create(['slug' => 'recamaras', 'is_published' => true]);
    Product::factory()->for($category, 'category')->draft()->create(['slug' => 'borrador']);

    $this->get('/og/product/borrador.png')->assertNotFound();
    $this->get('/og/product/no-existe.png')->assertNotFound();
    $this->get('/og/page/no-existe.png')->assertNotFound();
    $this->get('/og/otro/algo.png')->assertNotFound();
});

it('marks private and transactional pages as noindex', function () {
    $html = $this->get('/login')->assertOk()->getContent();
    expect(rawHead($html))->toContain('<meta name="robots" content="noindex, nofollow"')
        ->and($html)->not->toContain('og:image');

    $solicitar = $this->get('/solicitar')->assertOk()->getContent();
    expect(rawHead($solicitar))->toContain('<meta name="robots" content="index, follow')
        ->toContain('/og/page/solicitar.png');
});

it('keeps titles within 60 characters and descriptions within 155', function () {
    $seo = app(Seo::class);

    expect($seo->fullTitle('Centro de entretenimiento flotante con repisas · Centros de entretenimiento'))
        ->toBe('Centro de entretenimiento flotante con repisas | ML Habitat')
        ->and(mb_strlen($seo->fullTitle(str_repeat('Clóset vestidor amplio ', 8))))->toBeLessThanOrEqual(60)
        ->and($seo->fullTitle('Cocinas integrales, clósets y muebles a la medida'))->toBe('Cocinas integrales, clósets y muebles | ML Habitat')
        ->and(mb_strlen($seo->description(str_repeat('Diseñamos y fabricamos muebles a la medida. ', 10))))->toBeLessThanOrEqual(155);
});

it('serves robots.txt, an image sitemap and the web manifest', function () {
    $category = ProductCategory::factory()->create(['slug' => 'zapateras', 'is_published' => true]);
    Product::factory()->for($category, 'category')->create(['slug' => 'zapatera-roble']);

    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /portal')
        ->assertSee('Disallow: /tecnico')
        ->assertSee('Disallow: /archivos')
        ->assertSee('Disallow: /subidas')
        ->assertSee('Disallow: /agendar-visita/pago')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
        ->assertSee(url('/catalogo/zapateras/zapatera-roble'), false)
        ->assertSee('<lastmod>', false);

    $manifest = $this->get('/site.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')->json();
    expect(collect($manifest['icons'])->pluck('purpose'))->toContain('maskable')
        ->and($manifest['lang'])->toBe('es-MX');
});

it('prints the Google verification tag configured from the admin', function () {
    $admin = F::user(UserRole::SuperAdmin);

    $this->actingAs($admin)
        ->put('/admin/configuracion/seo', [
            'google_site_verification' => '<meta name="google-site-verification" content="abc123_XYZ-9" />',
            'bing_site_verification' => '',
        ])
        ->assertSessionHasNoErrors();

    expect(Settings::get('seo.google_site_verification'))->toBe('abc123_XYZ-9')
        ->and(Settings::get('seo.title'))->not->toBeNull();

    auth()->logout();
    expect($this->get('/preguntas-frecuentes')->getContent())
        ->toContain('<meta name="google-site-verification" content="abc123_XYZ-9">');

    $this->actingAs($admin)
        ->put('/admin/configuracion/seo', ['google_site_verification' => 'no válido <script>'])
        ->assertSessionHasErrors('google_site_verification');
});

it('redirects http and www to the canonical https host in production only', function () {
    config(['app.url' => 'https://mlhabitat.com.mx']);

    $this->get('http://www.mlhabitat.com.mx/preguntas-frecuentes?x=1')->assertOk();

    $this->app['env'] = 'production';

    $this->get('http://www.mlhabitat.com.mx/preguntas-frecuentes?x=1')
        ->assertStatus(301)
        ->assertRedirect('https://mlhabitat.com.mx/preguntas-frecuentes?x=1');

    $this->get('http://mlhabitat.com.mx/catalogo')
        ->assertStatus(301)
        ->assertRedirect('https://mlhabitat.com.mx/catalogo');

    // Otros hosts (IP del balanceador) y métodos no seguros no se tocan.
    expect($this->post('http://www.mlhabitat.com.mx/solicitar', [])->getStatusCode())->not->toBe(301);
});
