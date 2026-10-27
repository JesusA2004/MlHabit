<?php

use App\Enums\MediaVariantType;
use App\Enums\UserRole;
use App\Jobs\GenerateImageVariants;
use App\Models\Media;
use App\Models\Product;
use App\Models\SiteSection;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('media_local');
    Storage::fake('media_private');
    Storage::fake('local');
    config(['media.avif' => false]);
});

it('uploads a valid image, detects its real type and generates variants', function () {
    $file = new UploadedFile(WebFixtures::jpeg(1200, 800), 'cocina.jpg', 'image/jpeg', null, true);

    $response = $this->actingAs(WebFixtures::staff(['media.manage']))
        ->postJson(route('admin.multimedia.store'), ['file' => $file, 'collection' => 'catalogo/productos'])
        ->assertCreated();

    $media = Media::query()->findOrFail($response->json('media.id'));
    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->visibility->value)->toBe('public')
        ->and($media->collection?->path)->toBe('catalogo/productos')
        ->and($media->variants()->pluck('type')->all())->toContain(MediaVariantType::Thumbnail->value, MediaVariantType::Webp960->value, MediaVariantType::Webp1600->value);
    Storage::disk('media_local')->assertExists($media->path);
});

it('rejects a PHP script disguised as a JPG and disallowed extensions', function () {
    $script = UploadedFile::fake()->createWithContent('foto.jpg', "<?php echo 'hola'; ?>");

    $this->actingAs(WebFixtures::staff(['media.manage']))
        ->postJson(route('admin.multimedia.store'), ['file' => $script])
        ->assertUnprocessable();

    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $this->actingAs(WebFixtures::staff(['media.manage']))
        ->postJson(route('admin.multimedia.store'), ['file' => $svg])
        ->assertUnprocessable();

    expect(Media::query()->count())->toBe(0);
});

it('requires media.manage for the library and public uploads', function () {
    $user = WebFixtures::staff(['leads.view'], UserRole::Sales);

    $this->actingAs($user)->get(route('admin.multimedia.index'))->assertForbidden();
    $this->actingAs($user)->postJson(route('subidas.store'), ['filename' => 'a.jpg', 'mimeType' => 'image/jpeg', 'totalSize' => 100, 'visibility' => 'public'])->assertForbidden();
});

it('lets any active user upload private files in chunks', function () {
    $customer = User::factory()->role(UserRole::Customer)->create();
    $path = WebFixtures::jpeg(300, 200);
    $bytes = (string) file_get_contents($path);

    $start = $this->actingAs($customer)->postJson(route('subidas.store'), [
        'filename' => 'evidencia.jpg', 'mimeType' => 'image/jpeg', 'totalSize' => strlen($bytes), 'visibility' => 'private', 'collection' => 'sitio/portada',
    ])->assertCreated();
    $uuid = $start->json('uuid');

    $this->actingAs($customer)->post(route('subidas.fragmentos', $uuid), [
        'chunk' => UploadedFile::fake()->createWithContent('evidencia.jpg', $bytes), 'chunkIndex' => 0,
    ])->assertOk();

    $done = $this->actingAs($customer)->postJson(route('subidas.completar', $uuid))->assertCreated();
    $media = Media::query()->findOrFail($done->json('media.id'));

    expect($media->visibility->value)->toBe('private')
        ->and($media->disk)->toBe('media_private')
        ->and(str_starts_with((string) $media->collection?->path, 'privado/'))->toBeTrue();

    // Otro usuario no puede continuar la sesión ajena.
    $this->actingAs(User::factory()->create())->getJson(route('subidas.show', $uuid))->assertForbidden();
});

it('rejects unknown extensions when starting an upload session', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('subidas.store'), ['filename' => 'virus.exe', 'mimeType' => 'application/octet-stream', 'totalSize' => 10, 'visibility' => 'private'])
        ->assertUnprocessable();
});

it('blocks deleting media that is in use and deletes unused media with its files', function () {
    $user = WebFixtures::staff(['media.manage']);
    $used = Media::factory()->create();
    $product = Product::factory()->create(['cover_media_id' => $used->id]);
    $inGallery = Media::factory()->create();
    $product->attachMedia($inGallery->id, 'gallery');
    $logo = Media::factory()->create();
    Settings::put('brand', ['logo_media_id' => $logo->id]);
    $inSection = Media::factory()->create();
    WebFixtures::sections();
    $hero = SiteSection::query()->where('type', 'hero')->firstOrFail();
    $hero->update(['content' => [...$hero->content, 'media_id' => $inSection->id]]);

    foreach ([$used, $inGallery, $logo, $inSection] as $media) {
        $this->actingAs($user)->deleteJson(route('admin.multimedia.destroy', $media))->assertStatus(409)->assertJsonStructure(['usages' => [['type', 'label']]]);
    }

    $free = Media::factory()->create();
    Storage::disk('media_local')->put($free->path, 'x');
    $this->actingAs($user)->deleteJson(route('admin.multimedia.destroy', $free))->assertOk();
    expect(Media::query()->find($free->id))->toBeNull();
    Storage::disk('media_local')->assertMissing($free->path);
});

it('updates metadata and replaces a file keeping id and attachments', function () {
    $user = WebFixtures::staff(['media.manage']);
    $media = Media::factory()->create();
    Storage::disk('media_local')->put($media->path, 'viejo');
    $product = Product::factory()->create();
    $product->attachMedia($media->id, 'gallery');

    $this->actingAs($user)->patchJson(route('admin.multimedia.update', $media), ['alt_text' => 'Cocina blanca', 'origin' => 'real_photo'])->assertOk();
    expect($media->refresh()->alt_text)->toBe('Cocina blanca')->and($media->origin)->toBe('real_photo');

    $file = new UploadedFile(WebFixtures::jpeg(640, 480), 'nueva.jpg', 'image/jpeg', null, true);
    $this->actingAs($user)->post(route('admin.multimedia.reemplazar', $media), ['file' => $file])->assertOk();

    $media->refresh();
    expect($media->original_filename)->toBe('nueva.jpg')
        ->and($media->width)->toBe(640)
        ->and($media->attachments()->count())->toBe(1)
        ->and($media->alt_text)->toBe('Cocina blanca');
    Storage::disk('media_local')->assertExists($media->path);
});

it('hides private files from the library unless the user has sensitive.view', function () {
    Media::factory()->create(['original_filename' => 'publica.jpg']);
    Media::factory()->private()->create(['original_filename' => 'privada.jpg']);

    $this->actingAs(WebFixtures::staff(['media.manage']))
        ->get(route('admin.multimedia.index', ['visibilidad' => 'private']))
        ->assertInertia(fn ($page) => $page->component('admin/multimedia/Index')->has('media.data', 1)->where('media.data.0.filename', 'publica.jpg'));

    $this->actingAs(WebFixtures::staff(['media.manage', 'sensitive.view'], UserRole::Credit))
        ->get(route('admin.multimedia.index', ['visibilidad' => 'private']))
        ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.filename', 'privada.jpg'));
});

it('generates responsive widths only up to the original size', function () {
    $media = Media::factory()->create(['width' => null, 'height' => null]);
    Storage::disk('media_local')->put($media->path, (string) file_get_contents(WebFixtures::jpeg(2000, 1000)));

    (new GenerateImageVariants($media->id))->handle();

    $variants = $media->variants()->get()->keyBy('type');
    expect($variants->keys()->sort()->values()->all())->toBe(['thumbnail', 'webp_1600', 'webp_2400', 'webp_960'])
        ->and($variants['thumbnail']->width)->toBe(480)
        ->and($variants['webp_960']->width)->toBe(960)
        ->and($variants['webp_1600']->width)->toBe(1600)
        ->and($variants['webp_2400']->width)->toBe(2000)
        ->and($media->refresh()->width)->toBe(2000)
        ->and($media->metadata['color'] ?? null)->toMatch('/^#[0-9a-f]{6}$/i');

    $small = Media::factory()->create();
    Storage::disk('media_local')->put($small->path, (string) file_get_contents(WebFixtures::jpeg(400, 300)));
    (new GenerateImageVariants($small->id))->handle();
    expect($small->variants()->pluck('type')->all())->toBe(['thumbnail']);
});

it('generates AVIF variants when GD supports it', function () {
    config(['media.avif' => true]);
    $media = Media::factory()->create();
    Storage::disk('media_local')->put($media->path, (string) file_get_contents(WebFixtures::jpeg(500, 300)));

    (new GenerateImageVariants($media->id))->handle();

    $types = $media->variants()->pluck('type')->all();
    expect(in_array('avif_480', $types, true))->toBe(function_exists('imageavif'));
})->skip(! function_exists('imageavif'), 'GD sin soporte AVIF');

it('prunes private orphan media older than 24 hours only', function () {
    $old = Media::factory()->private()->create(['created_at' => now()->subHours(30)]);
    Storage::disk('media_private')->put($old->path, 'x');
    $recent = Media::factory()->private()->create();
    $attached = Media::factory()->private()->create(['created_at' => now()->subHours(30)]);
    Product::factory()->create()->attachMedia($attached->id, 'document');
    $publicOld = Media::factory()->create(['created_at' => now()->subDays(3)]);

    $this->artisan('media:prune-orphans')->assertSuccessful();

    expect(Media::query()->find($old->id))->toBeNull()
        ->and(Media::withTrashed()->find($old->id))->not->toBeNull()
        ->and(Media::query()->find($recent->id))->not->toBeNull()
        ->and(Media::query()->find($attached->id))->not->toBeNull()
        ->and(Media::query()->find($publicOld->id))->not->toBeNull();
    Storage::disk('media_private')->assertMissing($old->path);
});
