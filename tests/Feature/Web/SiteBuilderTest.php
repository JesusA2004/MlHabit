<?php

use App\Cms\Layout\Blocks;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\LegacyLayouts;
use App\Cms\Layout\Templates;
use App\Enums\SiteSectionType;
use App\Enums\UserRole;
use App\Models\Media;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Services\MediaUsageService;
use App\Services\Web\SiteBuilder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

/** Lienzo mínimo válido con los bloques dados. */
function canvas(array $blocks, array $settings = []): array
{
    return Blocks::layout($settings, $blocks);
}

/** Borrador actual de la portada (como lo envía el editor). */
function draftSections(): array
{
    return app(SiteBuilder::class)->draft('home')->snapshot['sections'];
}

/** Cambia el título de la portada en un arreglo de secciones. */
function withHeroTitle(array $sections, string $title): array
{
    foreach ($sections as $i => $section) {
        if ($section['type'] !== 'hero') {
            continue;
        }
        foreach ($section['layout']['blocks'] as $j => $block) {
            if ($block['type'] === 'heading') {
                $sections[$i]['layout']['blocks'][$j]['props']['text'] = $title;

                return $sections;
            }
        }
    }

    return $sections;
}

function heroTitle(array $sections): ?string
{
    foreach ($sections as $section) {
        foreach ($section['layout']['blocks'] ?? [] as $block) {
            if ($block['type'] === 'heading' && $block['props']['level'] === 'h1') {
                return $block['props']['text'];
            }
        }
    }

    return null;
}

// ---------------------------------------------------------------------
// Conversión y plantillas
// ---------------------------------------------------------------------

it('converts every legacy section into a valid layout', function (SiteSectionType $type) {
    $layout = LegacyLayouts::for($type, $type->schema()->defaults());
    $clean = LayoutValidator::layout($layout);

    expect($clean['blocks'])->not->toBeEmpty()
        ->and($clean['version'])->toBe(1);

    foreach ($clean['blocks'] as $block) {
        expect($block['x'] + $block['w'])->toBeLessThanOrEqual(12);
    }
})->with(SiteSectionType::curated());

it('reproduces the curated design with dynamic blocks and editable text', function () {
    $hero = LegacyLayouts::for(SiteSectionType::Hero, SiteSectionType::Hero->schema()->defaults());
    $types = array_column($hero['blocks'], 'type');

    expect($types)->toContain('heading', 'text', 'button', 'badge')
        ->and($hero['settings']['overlayHeader'])->toBeTrue()
        ->and(collect($hero['blocks'])->firstWhere('type', 'heading')['props']['level'])->toBe('h1');

    $process = LegacyLayouts::for(SiteSectionType::Process, SiteSectionType::Process->schema()->defaults());
    $steps = collect($process['blocks'])->firstWhere('type', 'process_steps');
    expect($steps['props']['steps'])->toHaveCount(7)
        ->and($process['settings']['anchor'])->toBe('como-funciona');

    // Ninguna sección convertida tiene más de un H1.
    $sections = array_map(fn (SiteSectionType $t) => ['isVisible' => true, 'layout' => LegacyLayouts::for($t, $t->schema()->defaults())], SiteSectionType::curated());
    expect(LayoutValidator::h1Count($sections))->toBe(1);
});

it('offers valid templates for every section kind', function () {
    $templates = Templates::all();

    expect(array_column($templates, 'id'))->toBe([
        'hero', 'text_image', 'gallery', 'categories', 'featured_projects', 'process', 'benefits',
        'payment_options', 'home_visit', 'coverage', 'testimonials', 'faq', 'final_cta', 'blank',
    ]);

    foreach ($templates as $template) {
        LayoutValidator::layout($template['layout']);
    }
});

it('renders legacy sections without a stored layout', function () {
    $section = SiteSection::factory()->legacy()->type(SiteSectionType::Faq)->create(['layout' => null]);

    expect($section->layout)->toBeNull()
        ->and(collect($section->resolvedLayout()['blocks'])->pluck('type'))->toContain('faq');
});

// ---------------------------------------------------------------------
// Validación del lienzo
// ---------------------------------------------------------------------

it('rejects unknown block types and out of bounds positions', function (array $block, string $error) {
    try {
        LayoutValidator::layout(canvas([$block]));
        $this->fail('Se esperaba un error de validación.');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain($error);
    }
})->with([
    'tipo desconocido' => [['id' => 'b1', 'type' => 'iframe', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'props' => []], 'layout.blocks.0.type'],
    'se sale a la derecha' => [Blocks::make('heading', 10, 0, 4, 3, ['text' => 'Hola']), 'layout.blocks.0.w'],
    'columna negativa' => [[...Blocks::make('heading', 0, 0, 4, 3, ['text' => 'Hola']), 'x' => -1], 'layout.blocks.0.x'],
    'alto cero' => [[...Blocks::make('text', 0, 0, 4, 3), 'h' => 0], 'layout.blocks.0.h'],
    'fila fuera de rango' => [[...Blocks::make('text', 0, 0, 4, 3), 'y' => 5000], 'layout.blocks.0.y'],
    'posición no numérica' => [[...Blocks::make('text', 0, 0, 4, 3), 'y' => '3; drop'], 'layout.blocks.0.y'],
    'movimiento inventado' => [Blocks::make('text', 0, 0, 4, 3, motion: ['preset' => 'explode']), 'layout.blocks.0.motion.preset'],
    'enlace con javascript' => [Blocks::make('button', 0, 0, 3, 2, ['label' => 'Ir', 'href' => 'javascript:alert(1)']), 'layout.blocks.0.props.href'],
    'enlace protocolo relativo' => [Blocks::make('button', 0, 0, 3, 2, ['label' => 'Ir', 'href' => '//evil.example']), 'layout.blocks.0.props.href'],
    'color fuera de la marca' => [Blocks::make('heading', 0, 0, 4, 3, ['text' => 'Hola', 'color' => 'red; background:url(x)']), 'layout.blocks.0.props.color'],
    'ícono no curado' => [Blocks::make('icon', 0, 0, 1, 2, ['name' => 'skull']), 'layout.blocks.0.props.name'],
    'nivel de título' => [Blocks::make('heading', 0, 0, 4, 3, ['text' => 'Hola', 'level' => 'h6']), 'layout.blocks.0.props.level'],
    'zoom excesivo' => [Blocks::make('image', 0, 0, 4, 8, ['zoom' => 9]), 'layout.blocks.0.props.zoom'],
    'opacidad' => [Blocks::make('text', 0, 0, 4, 3, style: ['opacity' => 140]), 'layout.blocks.0.style.opacity'],
]);

it('rejects duplicated block ids and unsafe section settings', function () {
    $a = Blocks::make('text', 0, 0, 4, 3);
    $b = [...Blocks::make('text', 4, 0, 4, 3), 'id' => $a['id']];

    expect(fn () => LayoutValidator::layout(canvas([$a, $b])))->toThrow(ValidationException::class);
    expect(fn () => LayoutValidator::layout(canvas([], ['anchor' => 'Hola Mundo!'])))->toThrow(ValidationException::class);
    expect(fn () => LayoutValidator::layout(canvas([], ['background' => ['kind' => 'video']])))->toThrow(ValidationException::class);
    expect(fn () => LayoutValidator::layout(canvas([], ['padding' => 'huge'])))->toThrow(ValidationException::class);
});

it('allows overlapping blocks for layering', function () {
    $clean = LayoutValidator::layout(canvas([
        Blocks::make('shape', 0, 0, 6, 10, ['kind' => 'rect'], z: 0),
        Blocks::make('heading', 1, 2, 4, 3, ['text' => 'Encima'], z: 2),
    ]));

    expect($clean['blocks'])->toHaveCount(2)
        ->and($clean['blocks'][1]['z'])->toBe(2);
});

it('strips markup from plain text and sanitizes rich text', function () {
    $clean = LayoutValidator::layout(canvas([
        Blocks::make('heading', 0, 0, 6, 3, ['text' => '<script>alert(1)</script><b>Cocinas</b> a la medida']),
        Blocks::make('text', 0, 4, 6, 4, ['html' => '<p onclick="steal()">Hola <strong>mundo</strong><script>bad()</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><a href="/catalogo">catálogo</a></p><iframe src="https://evil"></iframe>']),
        Blocks::make('badge', 0, 9, 3, 1, ['text' => '<img src=x onerror=alert(1)>Nuevo']),
    ]));

    $heading = $clean['blocks'][0]['props']['text'];
    $html = $clean['blocks'][1]['props']['html'];

    expect($heading)->not->toContain('<')->toContain('Cocinas a la medida')
        ->and($html)->toContain('<strong>mundo</strong>')->toContain('href="/catalogo"')
        ->not->toContain('onclick')->not->toContain('<script')->not->toContain('<img')
        ->not->toContain('javascript:')->not->toContain('iframe')
        ->and($clean['blocks'][2]['props']['text'])->toBe('Nuevo');
});

it('accepts only public media of the right kind', function () {
    $public = Media::factory()->create();
    $private = Media::factory()->private()->create();

    $ok = LayoutValidator::layout(canvas([Blocks::make('image', 0, 0, 6, 10, ['mediaId' => $public->id, 'focalX' => 30, 'focalY' => 70, 'zoom' => 1.5])]));
    expect($ok['blocks'][0]['props'])->toMatchArray(['mediaId' => $public->id, 'focalX' => 30, 'focalY' => 70, 'zoom' => 1.5]);

    expect(fn () => LayoutValidator::layout(canvas([Blocks::make('image', 0, 0, 6, 10, ['mediaId' => $private->id])])))->toThrow(ValidationException::class);
    expect(fn () => LayoutValidator::layout(canvas([], ['background' => ['kind' => 'image', 'mediaId' => $private->id]])))->toThrow(ValidationException::class);
    expect(fn () => LayoutValidator::layout(canvas([Blocks::make('image', 0, 0, 6, 10, ['mediaId' => 999999])])))->toThrow(ValidationException::class);
    // Una imagen no sirve como video.
    expect(fn () => LayoutValidator::layout(canvas([Blocks::make('video', 0, 0, 6, 10, ['mediaId' => $public->id])])))->toThrow(ValidationException::class);
    // Ni en los datos de un bloque dinámico.
    expect(fn () => LayoutValidator::layout(canvas([Blocks::make('final_cta', 0, 0, 12, 18, ['media_id' => $private->id])])))->toThrow(ValidationException::class);
});

it('keeps business rules inside dynamic blocks', function () {
    $block = Blocks::make('payment_options', 0, 0, 6, 12, ['items' => [['title' => 'Crédito aprobado al instante', 'body' => '']]]);

    expect(fn () => LayoutValidator::layout(canvas([$block])))->toThrow(ValidationException::class);

    $empty = Blocks::make('payment_options', 0, 0, 6, 12, ['disclaimer' => '']);
    expect(fn () => LayoutValidator::layout(canvas([$empty])))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------
// Borrador, publicación, historial
// ---------------------------------------------------------------------

it('keeps draft edits off the public site until they are published', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $draft = app(SiteBuilder::class)->draft('home');

    $this->actingAs($user)
        ->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => withHeroTitle(draftSections(), 'Cocinas que se viven')])
        ->assertOk()
        ->assertJsonPath('revision', $draft->revision + 1)
        ->assertJsonPath('hasChanges', true);

    // El sitio sigue mostrando lo publicado.
    $this->get('/')->assertInertia(fn ($page) => $page->where('sections', fn ($s) => heroTitle(collect($s)->all()) !== 'Cocinas que se viven'));

    $this->actingAs($user)
        ->postJson(route('admin.sitio.editor.publicar'), ['note' => 'Nuevo título de portada'])
        ->assertOk()
        ->assertJsonPath('draft.hasChanges', false)
        ->assertJsonPath('versions.0.number', 1)
        ->assertJsonPath('versions.0.note', 'Nuevo título de portada');

    $this->get('/')->assertInertia(fn ($page) => $page->component('public/Home')
        ->where('sections.0.type', 'hero')
        ->where('sections', fn ($s) => heroTitle(collect($s)->all()) === 'Cocinas que se viven')
        ->has('sections.0.layout.blocks'));

    expect(SitePageVersion::query()->count())->toBe(1);
    $this->assertDatabaseHas('activity_logs', ['action' => 'sitio.pagina_publicada']);
});

it('rejects stale autosaves instead of overwriting newer work', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage']);
    $draft = app(SiteBuilder::class)->draft('home');

    $this->actingAs($user)->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => draftSections()])->assertOk();
    $this->actingAs($user)->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => draftSections()])
        ->assertStatus(409)
        ->assertJsonPath('revision', $draft->revision + 1);
});

it('returns field paths for invalid drafts and stores nothing', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage']);
    $draft = app(SiteBuilder::class)->draft('home');
    $sections = draftSections();
    $sections[0]['layout']['blocks'][0]['x'] = 11;
    $sections[0]['layout']['blocks'][0]['w'] = 6;

    $this->actingAs($user)
        ->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => $sections])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sections.0.layout.blocks.0.w']);

    expect($draft->fresh()?->revision)->toBe($draft->revision);
});

it('adds, removes and reorders sections on publish', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $draft = app(SiteBuilder::class)->draft('home');
    $sections = draftSections();

    // Quita testimonios, mueve preguntas al inicio después de la portada y
    // agrega un lienzo "Texto + imagen".
    $sections = array_values(array_filter($sections, fn ($s) => $s['type'] !== 'testimonials'));
    $template = collect(Templates::all())->firstWhere('id', 'text_image');
    $sections[] = ['key' => (string) Str::uuid(), 'type' => 'canvas', 'isVisible' => true, 'content' => [], 'layout' => $template['layout']];

    $this->actingAs($user)->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => $sections])->assertOk();
    $this->actingAs($user)->postJson(route('admin.sitio.editor.publicar'))->assertOk();

    $types = SiteSection::query()->forPage('home')->get()->map(fn ($s) => $s->type->value)->all();
    expect($types)->not->toContain('testimonials')
        ->and(end($types))->toBe('canvas')
        ->and(count($types))->toBe(11);
});

it('enforces a single h1 when publishing', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $draft = app(SiteBuilder::class)->draft('home');
    $sections = draftSections();
    $sections[1]['layout']['blocks'][] = Blocks::make('heading', 0, 60, 6, 3, ['text' => 'Otro título principal', 'level' => 'h1']);

    // El borrador lo admite (el editor avisa), la publicación no.
    $this->actingAs($user)->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => $sections])->assertOk();
    $this->actingAs($user)->postJson(route('admin.sitio.editor.publicar'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sections']);

    expect(SitePageVersion::query()->count())->toBe(0);
});

it('restores a previous version into the draft', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage', 'content.publish']);
    $builder = app(SiteBuilder::class);

    $builder->saveDraft('home', ['sections' => withHeroTitle(draftSections(), 'Versión uno')], $builder->draft('home')->revision, $user);
    $first = $builder->publish('home', $user);
    $builder->saveDraft('home', ['sections' => withHeroTitle(draftSections(), 'Versión dos')], $builder->draft('home')->revision, $user);
    $builder->publish('home', $user);

    $this->actingAs($user)
        ->postJson(route('admin.sitio.editor.versiones.restaurar', $first))
        ->assertOk()
        ->assertJsonPath('draft.hasChanges', true)
        ->assertJsonStructure(['sections' => [['key', 'type', 'layout', 'images', 'data']]]);

    expect(heroTitle(draftSections()))->toBe('Versión uno');
    // Lo publicado no cambia hasta volver a publicar.
    $this->get('/')->assertInertia(fn ($page) => $page->where('sections', fn ($s) => heroTitle(collect($s)->all()) === 'Versión dos'));
});

it('discards the draft back to the published page', function () {
    WebFixtures::sections();
    $user = WebFixtures::staff(['content.manage']);
    $builder = app(SiteBuilder::class);
    $builder->saveDraft('home', ['sections' => withHeroTitle(draftSections(), 'Sin publicar')], $builder->draft('home')->revision, $user);

    $this->actingAs($user)->postJson(route('admin.sitio.editor.descartar'))->assertOk()->assertJsonPath('draft.hasChanges', false);

    expect(heroTitle(draftSections()))->not->toBe('Sin publicar');
});

// ---------------------------------------------------------------------
// Permisos, editor y vista previa
// ---------------------------------------------------------------------

it('lets content.manage edit the draft but not publish', function () {
    WebFixtures::sections();
    $editor = WebFixtures::staff(['content.manage']);
    $draft = app(SiteBuilder::class)->draft('home');

    $this->actingAs($editor)->get(route('admin.sitio.editor.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/sitio/Editor')->where('can.publish', false));
    $this->actingAs($editor)->putJson(route('admin.sitio.editor.borrador'), ['revision' => $draft->revision, 'sections' => draftSections()])->assertOk();
    $this->actingAs($editor)->postJson(route('admin.sitio.editor.publicar'))->assertForbidden();

    $outsider = WebFixtures::staff(['dashboard.view'], UserRole::Quality);
    $this->actingAs($outsider)->putJson(route('admin.sitio.editor.borrador'), ['revision' => 1, 'sections' => []])->assertForbidden();
    $this->actingAs($outsider)->postJson(route('admin.sitio.editor.descartar'))->assertForbidden();
});

it('renders the visual editor with everything it needs', function () {
    WebFixtures::sections();

    $this->actingAs(WebFixtures::admin())->get(route('admin.sitio.editor.edit'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/sitio/Editor')
            ->has('sections', 11)
            ->has('sections.0.layout.blocks')
            ->has('templates', 14)
            ->has('dynamic.process_steps.fields')
            ->has('icons')
            ->where('can.publish', true)
            ->where('draft.hasChanges', false)
            ->where('previewUrl', fn ($url) => str_contains($url, 'signature=')));

    expect(SitePageDraft::query()->count())->toBe(1);
});

it('previews the draft only through a signed link', function () {
    WebFixtures::sections();
    $admin = WebFixtures::admin();
    $builder = app(SiteBuilder::class);
    $builder->saveDraft('home', ['sections' => withHeroTitle(draftSections(), 'Así se verá')], $builder->draft('home')->revision, $admin);

    $this->actingAs($admin)->get(route('admin.sitio.editor.vista-previa'))->assertForbidden();

    $this->actingAs($admin)->get(URL::temporarySignedRoute('admin.sitio.editor.vista-previa', now()->addHour()))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('public/Home')
            ->where('preview', true)
            ->where('sections', fn ($s) => heroTitle(collect($s)->all()) === 'Así se verá'));
});

it('protects media used by the draft from deletion', function () {
    WebFixtures::sections();
    $media = Media::factory()->create();
    $admin = WebFixtures::admin();
    $builder = app(SiteBuilder::class);
    $sections = draftSections();
    $sections[0]['layout']['blocks'][] = Blocks::make('image', 0, 40, 6, 10, ['mediaId' => $media->id]);
    $builder->saveDraft('home', ['sections' => $sections], $builder->draft('home')->revision, $admin);

    expect(collect(app(MediaUsageService::class)->usages($media))->pluck('type'))->toContain('Borrador del editor visual');

    $builder->publish('home', $admin);
    expect(collect(app(MediaUsageService::class)->usages($media))->pluck('type'))->toContain('Portada del sitio');
});

it('resolves images, dynamic data and hides empty live sections on the home', function () {
    WebFixtures::sections();
    $media = Media::factory()->create();
    $admin = WebFixtures::admin();
    $builder = app(SiteBuilder::class);
    $sections = draftSections();
    $sections[0]['layout']['settings']['background'] = ['kind' => 'image', 'mediaId' => $media->id, 'overlay' => 50];
    $builder->saveDraft('home', ['sections' => $sections], $builder->draft('home')->revision, $admin);
    $builder->publish('home', $admin);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->where('sections.0.layout.settings.background.mediaId', $media->id)
        ->has('sections.0.images.'.$media->id)
        ->where('sections', fn ($s) => collect($s)->pluck('type')->doesntContain('categories')
            && collect($s)->firstWhere('type', 'process')['layout']['settings']['anchor'] === 'como-funciona'));
});
