<?php

use App\Cms\Layout\HomeBlueprint;
use App\Cms\Layout\HomeRedesign;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\Legacy\HomeV1Layouts;
use App\Enums\SiteSectionType;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Services\Web\SiteBuilder;
use Illuminate\Support\Str;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
});

/** Portada de fábrica anterior (v1), tal como la sembraba la versión previa. */
function seedFactoryHomeV1(): void
{
    foreach (SiteBuilder::RECOMMENDED_ORDER as $i => $type) {
        $content = $type->schema()->defaults();
        SiteSection::query()->create([
            'key' => (string) Str::uuid(),
            'page' => 'home',
            'type' => $type,
            'sort' => ($i + 1) * 10,
            'is_visible' => true,
            'content' => $content,
            'layout' => HomeV1Layouts::for($type, $content),
            'layout_version' => 1,
        ]);
    }
}

/** Una página propia publicada que el rediseño nunca debe tocar. */
function seedCustomPage(): SiteSection
{
    return SiteSection::query()->create([
        'key' => (string) Str::uuid(),
        'page' => 'pagina-7',
        'type' => SiteSectionType::Canvas,
        'sort' => 10,
        'is_visible' => true,
        'content' => [],
        'layout' => HomeBlueprint::trust(),
        'layout_version' => 1,
    ]);
}

it('builds a valid default home with one h1 and the anchors other pages link to', function () {
    $snapshot = LayoutValidator::snapshot(['sections' => HomeBlueprint::sections()]);
    $anchors = collect($snapshot['sections'])->pluck('layout.settings.anchor')->filter()->values()->all();

    expect($snapshot['sections'][0]['type'])->toBe('hero')
        ->and($snapshot['sections'][0]['layout']['settings']['overlayHeader'])->toBeTrue()
        ->and(LayoutValidator::h1Count($snapshot['sections']))->toBe(1)
        ->and($anchors)->toContain('como-funciona', 'visita', 'pagos');

    $blockTypes = collect($snapshot['sections'])->flatMap(fn ($s) => collect($s['layout']['blocks'])->pluck('type'))->all();
    expect($blockTypes)->toContain('categories', 'featured_projects', 'process_steps', 'home_visit', 'coverage_checker', 'service_pricing', 'payment_options', 'testimonials', 'faq', 'final_cta');
});

it('backs up and replaces a factory home, resets the draft and keeps custom pages', function () {
    seedFactoryHomeV1();
    $custom = seedCustomPage();
    $customLayout = $custom->fresh()->layout;
    $before = SiteSection::query()->forPage('home')->pluck('key')->all();

    expect(app(HomeRedesign::class)->apply())->toBe(HomeRedesign::REPLACED);

    $backup = SitePageVersion::query()->where('page', 'home')->where('note', HomeRedesign::BACKUP_NOTE)->firstOrFail();
    expect(collect($backup->snapshot['sections'])->pluck('key')->all())->toBe($before);

    $published = SiteSection::query()->forPage('home')->get();
    expect($published)->toHaveCount(count(HomeBlueprint::sections()))
        ->and($published->first()->type)->toBe(SiteSectionType::Hero)
        ->and(SitePageVersion::query()->where('page', 'home')->where('note', HomeRedesign::PUBLISHED_NOTE)->exists())->toBeTrue();

    // El borrador queda igual a lo publicado.
    $draft = SitePageDraft::query()->where('page', 'home')->firstOrFail();
    expect(collect($draft->snapshot['sections'])->pluck('key')->all())->toBe($published->pluck('key')->all())
        ->and(app(SiteBuilder::class)->hasUnpublishedChanges($draft))->toBeFalse();

    // Las páginas propias no se tocan.
    expect($custom->fresh()->layout)->toBe($customLayout)
        ->and(SitePageVersion::query()->where('page', 'pagina-7')->exists())->toBeFalse();

    // El respaldo se puede restaurar desde el editor.
    app(SiteBuilder::class)->restore($backup, WebFixtures::admin());
    expect(SitePageDraft::query()->where('page', 'home')->firstOrFail()->snapshot['sections'])->toHaveCount(11);
});

it('is idempotent', function () {
    seedFactoryHomeV1();
    $redesign = app(HomeRedesign::class);

    expect($redesign->apply())->toBe(HomeRedesign::REPLACED);
    $versions = SitePageVersion::query()->count();
    $keys = SiteSection::query()->forPage('home')->pluck('key')->all();

    expect($redesign->apply())->toBe(HomeRedesign::ALREADY)
        ->and(SitePageVersion::query()->count())->toBe($versions)
        ->and(SiteSection::query()->forPage('home')->pluck('key')->all())->toBe($keys);
});

it('keeps a customized home and offers the new one in the history', function () {
    seedFactoryHomeV1();
    $admin = WebFixtures::admin();
    $builder = app(SiteBuilder::class);

    // El dueño cambia el título de la portada y publica.
    $draft = $builder->draft('home');
    $snapshot = $draft->snapshot;
    foreach ($snapshot['sections'][0]['layout']['blocks'] as $i => $block) {
        if ($block['type'] === 'heading') {
            $snapshot['sections'][0]['layout']['blocks'][$i]['props']['text'] = 'Cocinas que enamoran';
        }
    }
    $builder->saveDraft('home', $snapshot, $draft->revision, $admin);
    $builder->publish('home', $admin);
    $keys = SiteSection::query()->forPage('home')->pluck('key')->all();

    expect(app(HomeRedesign::class)->apply())->toBe(HomeRedesign::KEPT)
        ->and(SiteSection::query()->forPage('home')->pluck('key')->all())->toBe($keys)
        ->and(SitePageVersion::query()->where('note', HomeRedesign::BACKUP_NOTE)->exists())->toBeTrue()
        ->and(SitePageVersion::query()->where('note', HomeRedesign::OFFERED_NOTE)->exists())->toBeTrue();

    $this->get('/')->assertOk()->assertSee('Cocinas que enamoran');
});

it('replaces an unpublished home whose draft has edits, saving the draft too', function () {
    seedFactoryHomeV1();
    $admin = WebFixtures::admin();
    $builder = app(SiteBuilder::class);
    $draft = $builder->draft('home');
    $snapshot = $draft->snapshot;
    $snapshot['sections'][0]['isVisible'] = false;
    $builder->saveDraft('home', $snapshot, $draft->revision, $admin);

    expect(app(HomeRedesign::class)->apply())->toBe(HomeRedesign::REPLACED)
        ->and(SitePageVersion::query()->where('note', HomeRedesign::DRAFT_NOTE)->exists())->toBeTrue();
});

it('detects the factory home ignoring block ids and number formats', function () {
    seedFactoryHomeV1();
    $sections = SiteSection::query()->forPage('home')->get();

    expect(app(HomeRedesign::class)->isFactoryDefault($sections))->toBeTrue();

    $hero = $sections->first();
    $layout = $hero->layout;
    $layout['blocks'][0]['x'] = 1;
    $hero->update(['layout' => $layout]);

    expect(app(HomeRedesign::class)->isFactoryDefault(SiteSection::query()->forPage('home')->get()))->toBeFalse();
});

it('installs the home from the command when there is none and renders example fallbacks', function () {
    $this->artisan('mlhabitat:redesign-home')->assertSuccessful();

    expect(SiteSection::query()->forPage('home')->count())->toBe(count(HomeBlueprint::sections()));

    // Sin catálogo, proyectos, testimonios ni preguntas: las secciones se
    // muestran con su contenido de ejemplo en vez de desaparecer.
    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->where('sections', fn ($s) => collect($s)->pluck('type')->contains('categories')
            && collect($s)->pluck('type')->contains('testimonials')
            && collect($s)->pluck('type')->contains('faq')
            && collect($s)->firstWhere('type', 'process')['layout']['settings']['anchor'] === 'como-funciona'));
});
