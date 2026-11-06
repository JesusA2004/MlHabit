<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\StorageProvider;
use App\Notifications\Platform\StorageFallbackActivated;
use App\Services\MediaUploadService;
use Database\Seeders\StorageProviderSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Platform\FlakyDisk;
use Tests\Feature\Platform\PlatformFixtures as F;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StorageProviderSeeder::class);
    Storage::fake('media_local');
    Storage::fake('media_private');
    config(['media.avif' => false, 'media.storage.retry_wait_seconds' => [0], 'media.storage.attempts' => 2]);

    $this->nas = FlakyDisk::install();

    StorageProvider::query()->update(['is_active' => false]);
    StorageProvider::query()->where('key', 'synology')->update([
        'is_active' => true,
        'last_test_result' => json_encode(['status' => 'ok']),
    ]);
});

function uploadPhoto(string $name = 'cocina.jpg'): Media
{
    $path = WebFixtures::jpeg(1200, 800);

    return app(MediaUploadService::class)->ingest($path, $name, 'image/jpeg', (int) filesize($path), 'sitio/general');
}

test('with the NAS reachable, uploads go straight to Synology', function () {
    $media = uploadPhoto();

    expect($media->disk)->toBe('media_synology')
        ->and($media->pending_sync_to)->toBeNull()
        ->and($media->checksum)->not->toBeNull()
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_synology']);

    Storage::disk('media_synology')->assertExists($media->path);
    Storage::disk('media_local')->assertMissing($media->path);
});

test('when the NAS is unreachable the upload is stored on the server and marked pending', function () {
    Notification::fake();
    $admin = F::user(UserRole::SuperAdmin);
    $this->nas->down = true;

    $media = uploadPhoto()->refresh();

    expect($media->disk)->toBe('media_local')
        ->and($media->pending_sync_to)->toBe('media_synology')
        ->and($media->sync_error)->not->toBeNull()
        ->and($media->variants()->count())->toBeGreaterThan(0)
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_local']);

    Storage::disk('media_local')->assertExists($media->path);

    Notification::assertSentTo($admin, StorageFallbackActivated::class);
    expect(ActivityLog::query()->where('action', 'almacenamiento.respaldo_local')->count())->toBe(1);
});

test('admins are notified only once per outage and the breaker skips the NAS', function () {
    Notification::fake();
    $admin = F::user(UserRole::SuperAdmin);
    $this->nas->down = true;

    uploadPhoto('uno.jpg');
    $callsAfterFirst = $this->nas->calls;
    uploadPhoto('dos.jpg');

    Notification::assertSentToTimes($admin, StorageFallbackActivated::class, 1);
    expect($this->nas->calls)->toBe($callsAfterFirst)
        ->and(Media::query()->where('pending_sync_to', 'media_synology')->count())->toBe(2);
});

test('the scheduled sync leaves files pending while the NAS is still down', function () {
    $this->nas->down = true;
    $media = uploadPhoto();

    $this->artisan('media:sync-pending')->assertSuccessful();

    expect($media->refresh()->disk)->toBe('media_local')
        ->and($media->pending_sync_to)->toBe('media_synology');
});

test('the scheduled sync pushes pending files and variants to the NAS once it is back', function () {
    $this->nas->down = true;
    $media = uploadPhoto()->refresh();
    $variantPaths = $media->variants()->pluck('path')->all();

    $this->nas->down = false;
    $this->artisan('media:sync-pending')->assertSuccessful();

    $media->refresh();
    expect($media->disk)->toBe('media_synology')
        ->and($media->pending_sync_to)->toBeNull()
        ->and($media->sync_error)->toBeNull()
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_synology']);

    Storage::disk('media_synology')->assertExists([$media->path, ...$variantPaths]);
    Storage::disk('media_local')->assertMissing([$media->path, ...$variantPaths]);
    expect(ActivityLog::query()->where('action', 'almacenamiento.sincronizado')->exists())->toBeTrue();
});

test('pending flags are cleared when Synology is no longer the active provider', function () {
    $this->nas->down = true;
    $media = uploadPhoto();

    StorageProvider::query()->update(['is_active' => false]);
    StorageProvider::query()->where('key', 'local')->update(['is_active' => true]);

    $this->artisan('media:sync-pending')->assertSuccessful();

    expect($media->refresh()->pending_sync_to)->toBeNull()
        ->and($media->disk)->toBe('media_local');
});

test('pending files can be synced on demand from the admin page', function () {
    $this->nas->down = true;
    $media = uploadPhoto();
    $this->nas->down = false;

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post('/admin/almacenamiento/sincronizar')
        ->assertSessionHas('success');

    expect($media->refresh()->disk)->toBe('media_synology')
        ->and($media->pending_sync_to)->toBeNull();
});

test('public streaming works for files on Synology and falls back to a kept server copy', function () {
    $media = uploadPhoto();
    expect($media->disk)->toBe('media_synology');

    $this->get('/media/'.$media->path)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Copia conservada en el servidor + NAS caído: se sirve la del servidor.
    Storage::disk('media_local')->put($media->path, (string) Storage::disk('media_synology')->get($media->path));
    $this->nas->down = true;

    $this->get('/media/'.$media->path)->assertOk();
});

test('deleting a media removes its files from whichever disk holds them', function () {
    $this->nas->down = true;
    $media = uploadPhoto()->refresh();
    $paths = [$media->path, ...$media->variants()->pluck('path')->all()];

    app(MediaUploadService::class)->deleteFiles($media);

    Storage::disk('media_local')->assertMissing($paths);
});
