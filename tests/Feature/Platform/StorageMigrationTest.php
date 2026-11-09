<?php

use App\Enums\StorageMigrationItemStatus;
use App\Enums\StorageMigrationStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\StorageMigration;
use App\Models\StorageProvider;
use App\Services\MediaUploadService;
use App\Services\Storage\StorageMigrationService;
use Database\Seeders\StorageProviderSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

    // El servidor es el proveedor activo; el NAS ya pasó la prueba de conexión.
    StorageProvider::query()->where('key', 'synology')->update(['last_test_result' => json_encode(['status' => 'ok'])]);
});

/** Sube una imagen con el servidor activo (queda en media_local con variantes). */
function localPhoto(string $name = 'sala.jpg'): Media
{
    $path = WebFixtures::jpeg(1000, 700);

    return app(MediaUploadService::class)->ingest($path, $name, 'image/jpeg', (int) filesize($path), 'sitio/general')->refresh();
}

/** @return list<string> */
function allPaths(Media $media): array
{
    return [$media->path, ...$media->variants()->pluck('path')->all()];
}

test('moving to Synology copies originals and variants, updates the DB and keeps the server copy by default', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $media = localPhoto();
    $paths = allPaths($media);
    expect($media->disk)->toBe('media_local');

    $this->actingAs($admin)
        ->post('/admin/almacenamiento/traslados', ['direction' => 'to_synology', 'keep_source' => true])
        ->assertSessionHas('success');

    $migration = StorageMigration::query()->sole();
    expect($migration->status)->toBe(StorageMigrationStatus::Completed)
        ->and($migration->items()->sole()->status)->toBe(StorageMigrationItemStatus::Done)
        ->and($migration->items()->sole()->source_kept)->toBeTrue();

    $media->refresh();
    expect($media->disk)->toBe('media_synology')
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_synology']);

    Storage::disk('media_synology')->assertExists($paths);
    Storage::disk('media_local')->assertExists($paths);

    // Sigue sirviéndose por /media desde el NAS.
    $this->get('/media/'.$media->path)->assertOk();

    expect(ActivityLog::query()->where('action', 'almacenamiento.traslado_iniciado')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action', 'almacenamiento.traslado_finalizado')->exists())->toBeTrue();

    // Liberar espacio: borra las copias del servidor tras volver a comprobar el NAS.
    $this->actingAs($admin)->post("/admin/almacenamiento/traslados/{$migration->id}/liberar-espacio")->assertSessionHas('success');

    Storage::disk('media_local')->assertMissing($paths);
    Storage::disk('media_synology')->assertExists($paths);
    expect($migration->refresh()->sources_purged_at)->not->toBeNull();
});

test('the artisan command with --delete-source removes the server copy only after verifying', function () {
    $media = localPhoto();
    $paths = allPaths($media);

    $this->artisan('media:migrate', ['--to' => 'synology', '--delete-source' => true])->assertSuccessful();

    expect($media->refresh()->disk)->toBe('media_synology');
    Storage::disk('media_synology')->assertExists($paths);
    Storage::disk('media_local')->assertMissing($paths);
});

test('a copy that fails verification is not committed and the source is kept', function () {
    $media = localPhoto();
    $this->nas->corruptWrites = true;

    $this->artisan('media:migrate', ['--to' => 'synology', '--delete-source' => true])->assertFailed();

    $item = StorageMigration::query()->sole()->items()->sole();
    expect($item->status)->toBe(StorageMigrationItemStatus::Failed)
        ->and($item->error)->toContain('huella')
        ->and($media->refresh()->disk)->toBe('media_local');

    Storage::disk('media_local')->assertExists($media->path);
    Storage::disk('media_synology')->assertMissing($media->path);
});

test('per-file failures while the NAS is down are reported and can be retried', function () {
    $admin = F::user(UserRole::SuperAdmin);
    $media = localPhoto();
    $this->nas->down = true;

    // El NAS cae con el traslado ya iniciado (el botón exige una prueba fresca).
    app(StorageMigrationService::class)->start('media_local', 'media_synology', false, $admin);

    $migration = StorageMigration::query()->sole();
    expect($migration->status)->toBe(StorageMigrationStatus::CompletedWithErrors)
        ->and($migration->items()->sole()->status)->toBe(StorageMigrationItemStatus::Failed)
        ->and($migration->items()->sole()->attempts)->toBe(2)
        ->and($media->refresh()->disk)->toBe('media_local');

    $this->actingAs($admin)->get('/admin/almacenamiento')->assertInertia(fn (Assert $page) => $page
        ->where('migration.counts.failed', 1)
        ->has('migration.failures', 1)
        ->where('migration.failures.0.filename', 'sala.jpg'));

    $this->nas->down = false;
    $this->actingAs($admin)->post("/admin/almacenamiento/traslados/{$migration->id}/reintentar")->assertSessionHas('success');

    expect($migration->refresh()->status)->toBe(StorageMigrationStatus::Completed)
        ->and($media->refresh()->disk)->toBe('media_synology');
    Storage::disk('media_local')->assertMissing($media->path);
});

test('migrations are idempotent and resume without re-sending identical copies', function () {
    $media = localPhoto();
    $service = app(StorageMigrationService::class);

    // Una copia idéntica ya existe en el NAS (traslado interrumpido antes).
    Storage::disk('media_synology')->put($media->path, (string) Storage::disk('media_local')->get($media->path));

    $this->artisan('media:migrate', ['--to' => 'synology'])->assertSuccessful();

    $first = StorageMigration::query()->sole();
    $item = $first->items()->sole();
    // Solo se transfirieron las variantes: el original ya estaba idéntico.
    expect($item->status)->toBe(StorageMigrationItemStatus::Done)
        ->and($item->transferred_bytes)->toBe($item->bytes - $media->size);

    // Volver a lanzarlo no encuentra nada que mover.
    expect($service->plan('media_local')['files'])->toBe(0);
    $this->artisan('media:migrate', ['--to' => 'synology'])->expectsOutput('No hay nada que mover.')->assertSuccessful();
    expect(StorageMigration::query()->count())->toBe(1);

    // Procesar otra vez el mismo renglón no hace nada.
    $service->processItem($item);
    expect($item->refresh()->status)->toBe(StorageMigrationItemStatus::Done)
        ->and($media->refresh()->disk)->toBe('media_synology');
});

test('dry-run reports what would move without touching anything', function () {
    $media = localPhoto();

    $this->artisan('media:migrate', ['--to' => 'synology', '--dry-run' => true])
        ->expectsOutputToContain('Archivos por mover: 1')
        ->expectsOutput('Simulación: no se movió nada.')
        ->assertSuccessful();

    expect(StorageMigration::query()->count())->toBe(0)
        ->and($media->refresh()->disk)->toBe('media_local');
    Storage::disk('media_synology')->assertMissing($media->path);
});

test('the reverse migration brings files back from Synology to the server', function () {
    $media = localPhoto();
    $paths = allPaths($media);
    $this->artisan('media:migrate', ['--to' => 'synology', '--delete-source' => true])->assertSuccessful();

    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post('/admin/almacenamiento/traslados', ['direction' => 'to_local', 'keep_source' => false])
        ->assertSessionHas('success');

    $media->refresh();
    expect($media->disk)->toBe('media_local')
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_local']);
    Storage::disk('media_local')->assertExists($paths);
    Storage::disk('media_synology')->assertMissing($paths);
});

test('private files never leave the server', function () {
    $private = Media::factory()->private()->create();
    Storage::disk('media_private')->put($private->path, 'pdf');

    expect(app(StorageMigrationService::class)->plan('media_local')['files'])->toBe(0);
    $this->artisan('media:migrate', ['--to' => 'synology'])->assertSuccessful();

    expect($private->refresh()->disk)->toBe('media_private');
});

test('a cancelled migration stops processing the remaining files', function () {
    localPhoto('a.jpg');
    localPhoto('b.jpg');
    $service = app(StorageMigrationService::class);

    $migration = $service->create('media_local', 'media_synology', true);
    $this->actingAs(F::user(UserRole::SuperAdmin))
        ->post("/admin/almacenamiento/traslados/{$migration->id}/cancelar")
        ->assertSessionHas('success');

    foreach ($migration->items as $item) {
        $service->processItem($item);
    }

    expect($migration->refresh()->status)->toBe(StorageMigrationStatus::Cancelled)
        ->and($migration->items()->where('status', 'pending')->count())->toBe(2)
        ->and(Media::query()->where('disk', 'media_local')->count())->toBe(2);
});

test('only one migration can run at a time and synology needs a passing test', function () {
    $admin = F::user(UserRole::SuperAdmin);
    localPhoto();

    // Sin conexión fresca con el NAS no se puede iniciar (aunque la última prueba guardada fuera buena).
    $this->nas->down = true;
    $this->actingAs($admin)->post('/admin/almacenamiento/traslados', ['direction' => 'to_synology', 'keep_source' => true])
        ->assertSessionHas('error');
    expect(StorageMigration::query()->count())->toBe(0);

    expect(StorageProvider::query()->where('key', 'synology')->first()->last_test_result['status'])->toBe('disconnected');

    $this->nas->down = false;
    app(StorageMigrationService::class)->create('media_local', 'media_synology', true);
    $this->actingAs($admin)->post('/admin/almacenamiento/traslados', ['direction' => 'to_synology', 'keep_source' => true])
        ->assertSessionHas('error');
    expect(StorageMigration::query()->count())->toBe(1);
});

test('storage actions require settings.manage', function () {
    $operations = F::user(UserRole::Operations);
    $migration = app(StorageMigrationService::class)->create('media_local', 'media_synology', true);

    $this->actingAs($operations)->post('/admin/almacenamiento/traslados', ['direction' => 'to_synology', 'keep_source' => true])->assertForbidden();
    $this->actingAs($operations)->post("/admin/almacenamiento/traslados/{$migration->id}/cancelar")->assertForbidden();
    $this->actingAs($operations)->post("/admin/almacenamiento/traslados/{$migration->id}/reintentar")->assertForbidden();
    $this->actingAs($operations)->post("/admin/almacenamiento/traslados/{$migration->id}/liberar-espacio")->assertForbidden();
    $this->actingAs($operations)->post('/admin/almacenamiento/sincronizar')->assertForbidden();

    auth()->logout();
    $this->post('/admin/almacenamiento/sincronizar')->assertRedirect('/login');
});

test('the storage page shows where files live and the pending sync count', function () {
    localPhoto();
    Media::factory()->create(['disk' => 'media_local', 'pending_sync_to' => 'media_synology', 'size' => 2048]);

    $this->actingAs(F::user(UserRole::SuperAdmin))->get('/admin/almacenamiento')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/almacenamiento/Index')
            ->where('storage.disks.0.disk', 'media_local')
            ->where('storage.disks.0.files', 2)
            ->where('storage.pendingSync', 1)
            ->where('plan.toSynology.files', 2)
            ->where('migration', null));
});
