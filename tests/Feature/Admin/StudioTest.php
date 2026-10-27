<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\StudioPage;
use App\Models\StudioVersion;
use App\Models\User;
use App\Studio\StudioDocumentValidator;
use App\Studio\StudioService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\StorageProviderSeeder;
use Database\Seeders\ThemeSettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('media_local');
    $this->seed([
        CategorySeeder::class,
        ThemeSettingsSeeder::class,
        SiteSettingsSeeder::class,
        HomepageSectionSeeder::class,
        PageSeeder::class,
        StorageProviderSeeder::class,
    ]);
});

function studioAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function homePage(): StudioPage
{
    return app(StudioService::class)->ensureSystemPage('home');
}

/**
 * @return array<string, mixed>
 */
function headingDocument(string $text = 'Hola', array $style = []): array
{
    return ['schema' => 1, 'sections' => [[
        'id' => 'n_sec00001', 'type' => 'hero', 'v' => 1,
        'props' => ['layout' => 'centered'],
        'style' => [], 'motion' => null, 'hidden' => false,
        'slots' => [
            'content' => [[
                'id' => 'n_head0001', 'type' => 'heading', 'v' => 1,
                'props' => ['text' => $text, 'level' => 'h1'],
                'style' => $style, 'motion' => null, 'hidden' => false,
            ]],
            'media' => [],
        ],
    ]]];
}

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

test('guests cannot open studio', function () {
    $this->get('/admin/studio')->assertRedirect('/login');
});

test('collaborators cannot open or save in studio', function () {
    $user = User::factory()->create(['role' => UserRole::Colaborador]);
    $page = homePage();

    $this->actingAs($user)->get('/admin/studio')->assertForbidden();
    $this->actingAs($user)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument(), 'revision' => 1])->assertForbidden();
});

test('studio index lists the system pages generated from the cms', function () {
    $this->actingAs(studioAdmin())
        ->get('/admin/studio')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/studio/Index')
            ->has('pages', 5)
            ->where('pages.0.key', 'home')
            ->where('pages.0.isPublished', true)
        );
});

test('the editor loads the draft, published document and resolved data', function () {
    $page = homePage();

    $this->actingAs(studioAdmin())
        ->get("/admin/studio/{$page->id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('admin/studio/Editor')
            ->has('document.sections')
            ->has('published.sections')
            ->has('resolved.media')
            ->where('page.revision', 1)
        );
});

// ---------------------------------------------------------------------
// Validación estricta
// ---------------------------------------------------------------------

test('validator rejects unknown block types without destroying the page', function () {
    $doc = headingDocument();
    $doc['sections'][0]['slots']['content'][] = ['id' => 'n_bad00001', 'type' => 'script', 'props' => ['code' => 'alert(1)']];

    $result = (new StudioDocumentValidator)->validate($doc);

    expect($result['errors'])->toHaveKey('sections.0.slots.content.1');
    expect($result['document']['sections'][0]['slots']['content'])->toHaveCount(1);
});

test('validator rejects javascript links, arbitrary colors and out of range sizes', function () {
    $doc = headingDocument('x', ['base' => ['color' => 'red; background:url(x)', 'fontSize' => 9999, 'unknownCss' => 'position:fixed']]);
    $doc['sections'][0]['slots']['content'][] = [
        'id' => 'n_btn00001', 'type' => 'buttons', 'v' => 1,
        'props' => ['items' => [['label' => 'X', 'link' => ['type' => 'url', 'value' => 'javascript:alert(1)'], 'variant' => 'primary']]],
        'style' => [], 'motion' => null, 'hidden' => false,
    ];

    $result = (new StudioDocumentValidator)->validate($doc);
    $heading = $result['document']['sections'][0]['slots']['content'][0];
    $button = $result['document']['sections'][0]['slots']['content'][1];

    expect($result['errors'])->toHaveKey('sections.0.slots.content.0.style.base.color');
    expect($heading['style']['base'])->not->toHaveKey('color');
    expect($heading['style']['base'])->not->toHaveKey('unknownCss');
    expect($heading['style']['base']['fontSize'])->toEqual(220);
    expect($button['props']['items'][0]['link']['value'])->not->toContain('javascript');
});

test('elements can only be placed in compatible slots', function () {
    $doc = headingDocument();
    $doc['sections'][0]['slots']['media'][] = ['id' => 'n_txt00001', 'type' => 'text', 'v' => 1, 'props' => ['text' => 'x'], 'style' => [], 'motion' => null, 'hidden' => false];

    $result = (new StudioDocumentValidator)->validate($doc);

    expect($result['errors'])->toHaveKey('sections.0.slots.media.0');
});

test('saving an invalid draft returns 422 and keeps the previous draft', function () {
    $page = homePage();
    $before = $page->draft;
    $doc = headingDocument();
    $doc['sections'][0]['type'] = 'iframe';

    $this->actingAs(studioAdmin())
        ->putJson("/admin/studio/{$page->id}/borrador", ['document' => $doc, 'revision' => 1])
        ->assertStatus(422);

    expect($page->refresh()->draft)->toEqual($before);
});

// ---------------------------------------------------------------------
// Borrador, concurrencia, publicación
// ---------------------------------------------------------------------

test('saving a draft increments the revision and does not change the public site', function () {
    $page = homePage();
    $admin = studioAdmin();

    $this->actingAs($admin)
        ->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('Borrador secreto'), 'revision' => 1])
        ->assertOk()
        ->assertJson(['revision' => 2]);

    expect($page->refresh()->draft['sections'][0]['slots']['content'][0]['props']['text'])->toBe('Borrador secreto');

    $this->get('/')->assertInertia(fn ($p) => $p
        ->component('public/Home')
        ->where('studio.document.sections.0.type', 'hero')
        ->where('studio.document.sections.0.slots.content.0.props.text', fn ($t) => $t !== 'Borrador secreto')
    );
});

test('a stale revision is rejected with 409 to prevent overwriting another session', function () {
    $page = homePage();
    $admin = studioAdmin();

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('A'), 'revision' => 1])->assertOk();
    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('B'), 'revision' => 1])
        ->assertStatus(409)
        ->assertJson(['revision' => 2]);

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('B'), 'revision' => 1, 'force' => true])
        ->assertOk();
    expect($page->refresh()->draft['sections'][0]['slots']['content'][0]['props']['text'])->toBe('B');
});

test('publishing creates a version and updates the public page', function () {
    $page = homePage();
    $admin = studioAdmin();

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('Publicado ya', ['base' => ['fontSize' => 82]]), 'revision' => 1]);
    $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/publicar", ['summary' => 'Título más grande'])
        ->assertOk()
        ->assertJsonPath('page.publishedNumber', 2);

    expect(StudioVersion::query()->where('studio_page_id', $page->id)->count())->toBe(2);

    $this->get('/')->assertInertia(fn ($p) => $p
        ->where('studio.document.sections.0.slots.content.0.props.text', 'Publicado ya')
        ->where('studio.document.sections.0.slots.content.0.style.base.fontSize', 82)
    );
});

test('publishing is blocked when a referenced media file no longer exists', function () {
    $page = homePage();
    $admin = studioAdmin();
    $doc = headingDocument();
    $doc['sections'][0]['slots']['media'][] = ['id' => 'n_img00001', 'type' => 'image', 'v' => 1, 'props' => ['media' => 999999], 'style' => [], 'motion' => null, 'hidden' => false];

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => $doc, 'revision' => 1])->assertOk();
    $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/publicar")
        ->assertStatus(422)
        ->assertJsonPath('problems.0', fn ($m) => str_contains($m, 'ya no existe'));
});

test('restoring a version copies it to the draft and backs up unpublished work', function () {
    $page = homePage();
    $admin = studioAdmin();
    $v1 = $page->versions()->first();

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('Trabajo sin publicar'), 'revision' => 1]);

    $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/versiones/{$v1->id}/restaurar")->assertOk();

    $page->refresh();
    expect($page->draft)->toEqual($v1->document);
    $snapshot = StudioVersion::query()->where('studio_page_id', $page->id)->where('kind', 'snapshot')->first();
    expect($snapshot)->not->toBeNull();
    expect($snapshot->document['sections'][0]['slots']['content'][0]['props']['text'])->toBe('Trabajo sin publicar');
});

test('discarding the draft returns it to the published document', function () {
    $page = homePage();
    $admin = studioAdmin();
    $published = $page->publishedVersion->document;

    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('Descartable'), 'revision' => 1]);
    $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/descartar")->assertOk();

    expect($page->refresh()->draft)->toEqual($published);
});

// ---------------------------------------------------------------------
// Vistas previas
// ---------------------------------------------------------------------

test('the draft preview requires authentication', function () {
    $page = homePage();

    $this->get("/admin/studio/{$page->id}/vista-previa")->assertRedirect('/login');
});

test('the shared preview link is signed and shows the draft', function () {
    $page = homePage();
    $admin = studioAdmin();
    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => headingDocument('Solo en preview'), 'revision' => 1]);

    $url = $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/compartir")->assertOk()->json('url');

    auth()->logout();
    $this->get($url)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($p) => $p->where('previewing', true)->where('studio.document.sections.0.slots.content.0.props.text', 'Solo en preview'));

    $this->get(URL::route('studio.shared-preview', ['page' => $page->id]))->assertForbidden();
});

// ---------------------------------------------------------------------
// Páginas personalizadas
// ---------------------------------------------------------------------

test('custom pages are created, hidden until published and served at their slug', function () {
    $admin = studioAdmin();

    $this->actingAs($admin)->post('/admin/studio', ['title' => 'Taller', 'slug' => 'taller'])->assertRedirect();
    $page = StudioPage::query()->where('slug', 'taller')->firstOrFail();

    $this->get('/taller')->assertNotFound();

    $this->actingAs($admin)->postJson("/admin/studio/{$page->id}/publicar")->assertOk();
    $this->get('/taller')->assertOk()->assertInertia(fn ($p) => $p->component('public/StudioPage'));
});

test('custom page slugs cannot take reserved routes', function () {
    $this->actingAs(studioAdmin())
        ->post('/admin/studio', ['title' => 'Admin', 'slug' => 'admin'])
        ->assertSessionHasErrors('slug');

    $this->actingAs(studioAdmin())
        ->post('/admin/studio', ['title' => 'Malo', 'slug' => '../etc'])
        ->assertSessionHasErrors('slug');
});

test('system pages cannot be deleted', function () {
    $page = homePage();

    $this->actingAs(studioAdmin())->delete("/admin/studio/{$page->id}")->assertForbidden();
});

// ---------------------------------------------------------------------
// Integridad multimedia y datos dinámicos
// ---------------------------------------------------------------------

test('deleting media used in a studio page is warned and the draft reference is cleared when forced', function () {
    $media = Media::factory()->create();
    $page = homePage();
    $admin = studioAdmin();
    $doc = headingDocument();
    $doc['sections'][0]['slots']['media'][] = ['id' => 'n_img00001', 'type' => 'image', 'v' => 1, 'props' => ['media' => $media->id], 'style' => [], 'motion' => null, 'hidden' => false];
    $this->actingAs($admin)->putJson("/admin/studio/{$page->id}/borrador", ['document' => $doc, 'revision' => 1]);

    $this->actingAs($admin)->deleteJson("/admin/multimedia/{$media->id}")
        ->assertStatus(409)
        ->assertJsonPath('usages.0.type', 'Studio (borrador)');

    $this->actingAs($admin)->deleteJson("/admin/multimedia/{$media->id}", ['force' => true])->assertOk();
    expect($page->refresh()->draft['sections'][0]['slots']['media'][0]['props']['media'])->toBeNull();
});

test('posts blocks resolve published posts dynamically instead of storing copies', function () {
    $page = homePage();
    $category = Category::query()->first();
    $published = Post::factory()->for($category)->published()->create();
    Post::factory()->for($category)->create(['status' => 'draft']);

    $doc = ['schema' => 1, 'sections' => [[
        'id' => 'n_posts001', 'type' => 'posts', 'v' => 1, 'props' => ['layout' => 'cards', 'source' => 'latest', 'limit' => 6],
        'style' => [], 'motion' => null, 'hidden' => false, 'slots' => [],
    ]]];

    $resolved = $this->actingAs(studioAdmin())->postJson("/admin/studio/{$page->id}/resolver", ['document' => $doc])->assertOk()->json();

    expect($resolved['feeds']['n_posts001'])->toHaveCount(1);
    expect($resolved['feeds']['n_posts001'][0]['slug'])->toBe($published->slug);
    expect(json_encode($doc))->not->toContain($published->title);
});
