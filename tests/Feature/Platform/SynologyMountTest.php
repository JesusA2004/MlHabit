<?php

use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Enums\UserRole;
use App\Exceptions\RejectedUploadException;
use App\Jobs\GenerateImageVariants;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use App\Models\StorageProvider;
use App\Services\FfmpegService;
use App\Services\MediaUploadService;
use App\Services\Storage\MediaStorage;
use App\Services\Storage\SynologyMount;
use App\Services\Storage\SynologyUnavailableException;
use Database\Seeders\StorageProviderSeeder;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Platform\FlakyDisk;
use Tests\Feature\Platform\PlatformFixtures as F;
use Tests\Feature\Web\WebFixtures;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StorageProviderSeeder::class);
    Storage::fake('media_local');
    Storage::fake('media_private');
    config(['media.avif' => false, 'media.storage.retry_wait_seconds' => [0], 'media.storage.attempts' => 2]);
    SynologyMount::flush();
});

afterEach(function () {
    SynologyMount::flush();
});

/** Carpeta vacía que simula /mnt/mlhabitat-storage y disco real (driver synology-mount). */
function realMountRoot(string $name = 'nas-mount'): string
{
    $root = storage_path('framework/testing/'.$name);
    (new Files)->deleteDirectory($root);
    (new Files)->ensureDirectoryExists($root);
    Storage::forgetDisk('media_synology');

    return $root;
}

/** @return list<string> */
function filesUnder(string $root): array
{
    return is_dir($root) ? array_map(fn ($f) => $f->getRelativePathname(), (new Files)->allFiles($root, true)) : [];
}

function activateSynology(): void
{
    StorageProvider::query()->update(['is_active' => false]);
    StorageProvider::query()->where('key', 'synology')->update(['is_active' => true]);
}

function nasPhoto(string $name = 'Cocina Integral.jpg'): Media
{
    $path = WebFixtures::jpeg(1200, 800);

    return app(MediaUploadService::class)->ingest($path, $name, 'image/jpeg', (int) filesize($path), 'sitio/general')->refresh();
}

function tinyMp4(): string
{
    $path = tempnam(sys_get_temp_dir(), 'mlh-mp4-');
    file_put_contents($path, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 2048));

    return $path;
}

/** FFmpeg simulado: duración fija y una portada JPEG real. */
function fakeFfmpeg(): void
{
    app()->instance(FfmpegService::class, new class extends FfmpegService
    {
        public function isAvailable(): bool
        {
            return true;
        }

        public function durationSeconds(string $absolutePath): ?int
        {
            return 12;
        }

        public function generateThumbnail(string $absoluteVideoPath, string $absoluteOutputPath, int $atSecond = 1): bool
        {
            return copy(WebFixtures::jpeg(320, 180), $absoluteOutputPath);
        }
    });
}

// ── Detección del montaje ─────────────────────────────────────────────

test('a valid CIFS mount of the expected source is detected', function () {
    $root = realMountRoot();
    FlakyDisk::mount($root);

    $status = app(SynologyMount::class)->mountStatus();

    expect($status['mounted'])->toBeTrue()
        ->and($status['fstype'])->toBe('cifs')
        ->and($status['source'])->toBe('//100.67.89.20/MLHabitat');
});

test('the source is compared case-insensitively and mount points with spaces are unescaped', function () {
    $root = realMountRoot('nas mount con espacio');
    FlakyDisk::mount($root, 'smb3', '\\\\100.67.89.20\\mlhabitat');

    expect(app(SynologyMount::class)->mountStatus()['mounted'])->toBeTrue();
});

test('without mountinfo the NAS is reported as not mounted', function () {
    config(['media.synology.mountinfo' => storage_path('framework/testing/no-existe')]);

    $status = app(SynologyMount::class)->mountStatus();
    $check = app(SynologyMount::class)->check();

    expect($status['mounted'])->toBeFalse()
        ->and($status['reason'])->not->toBe('')
        ->and($check['status'])->toBe('disconnected')
        ->and($check['state'])->toBe('disconnected')
        ->and($check['capacity'])->toBeNull();
});

test('an existing local directory without the NAS mounted is never written to', function () {
    $root = realMountRoot();
    FlakyDisk::mount($root, fstype: null);
    activateSynology();

    expect(fn () => app(SynologyMount::class)->assertAvailable())->toThrow(SynologyUnavailableException::class);
    expect(fn () => Storage::disk('media_synology')->put('x.txt', 'hola'))->toThrow(SynologyUnavailableException::class);

    // La subida cae al servidor y la carpeta del VPS sigue vacía.
    $media = nasPhoto();
    expect($media->disk)->toBe('media_local')
        ->and($media->pending_sync_to)->toBe('media_synology')
        ->and(filesUnder($root))->toBe([]);

    $check = app(SynologyMount::class)->check();
    expect($check['state'])->toBe('disconnected')
        ->and(filesUnder($root))->toBe([]);
});

test('a mount with the wrong source or filesystem type is an error and is not used', function (?string $fstype, string $source) {
    $root = realMountRoot();
    FlakyDisk::mount($root, $fstype, $source);

    $status = app(SynologyMount::class)->mountStatus();
    $check = app(SynologyMount::class)->check();

    expect($status['mounted'])->toBeFalse()
        ->and($status['problem'])->toBe('mismatch')
        ->and($check['status'])->toBe('error')
        ->and($check['state'])->toBe('error')
        ->and($check['checks']['mount'])->toBeFalse();
    expect(fn () => Storage::disk('media_synology')->put('x.txt', 'hola'))->toThrow(SynologyUnavailableException::class);
    expect(filesUnder($root))->toBe([]);
})->with([
    'otro origen' => ['cifs', '//100.67.89.99/Otro'],
    'otro tipo' => ['nfs', '//100.67.89.20/MLHabitat'],
]);

test('the real driver writes atomically through the mount and exposes a local path', function () {
    $root = realMountRoot();
    FlakyDisk::mount($root);

    $disk = Storage::disk('media_synology');
    expect($disk->put('sitio/a.txt', 'contenido'))->toBeTrue()
        ->and($disk->get('sitio/a.txt'))->toBe('contenido')
        ->and(str_replace('\\', '/', $disk->path('sitio/a.txt')))->toBe(str_replace('\\', '/', $root).'/sitio/a.txt')
        ->and(filesUnder($root))->toBe(['sitio'.DIRECTORY_SEPARATOR.'a.txt']);

    $check = app(SynologyMount::class)->check();
    expect($check['status'])->toBe('ok')
        ->and($check['checks'])->toBe(['mount' => true, 'reachable' => true, 'write' => true, 'read' => true, 'delete' => true])
        ->and($check['capacity']['totalBytes'])->toBeGreaterThan(0)
        ->and($check['capacity']['usedBytes'] + $check['capacity']['freeBytes'])->toBe($check['capacity']['totalBytes']);

    // La prueba no deja temporales.
    expect(collect(filesUnder($root))->filter(fn ($f) => str_contains($f, '.storage-check') && str_ends_with($f, '.tmp'))->all())->toBe([]);
});

test('a permission failure is reported as an error and uploads fall back to the server', function () {
    $nas = FlakyDisk::install();
    $nas->failWrites = true;
    activateSynology();

    $check = app(SynologyMount::class)->check();
    expect($check['state'])->toBe('error')
        ->and($check['checks']['mount'])->toBeTrue()
        ->and($check['checks']['write'])->toBeFalse();

    $media = nasPhoto();
    expect($media->disk)->toBe('media_local')
        ->and($media->pending_sync_to)->toBe('media_synology')
        ->and($nas->files())->toBe([]);
});

test('a write interrupted midway leaves neither a partial file nor a .part temporary', function () {
    $nas = FlakyDisk::install();
    $nas->failMidWrite = true;

    expect(Storage::disk('media_synology')->put('sitio/video.mp4', str_repeat('x', 4096)))->toBeFalse();

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, str_repeat('y', 4096));
    rewind($stream);
    expect(Storage::disk('media_synology')->writeStream('sitio/otro.mp4', $stream))->toBeFalse();

    expect($nas->files())->toBe([]);
});

test('the reachability check detects whether the NAS answers on its port', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

    $root = realMountRoot();
    $source = '//127.0.0.1/MLHabitat';
    FlakyDisk::mount($root, 'cifs', $source);
    config(['media.synology.source' => $source, 'media.synology.port' => $port, 'media.synology.reachability_check' => true, 'media.synology.connect_timeout' => 1]);
    SynologyMount::flush();

    expect(app(SynologyMount::class)->check()['status'])->toBe('ok');

    fclose($server);
    $check = app(SynologyMount::class)->check();
    expect($check['status'])->toBe('disconnected')
        ->and($check['checks']['mount'])->toBeTrue()
        ->and($check['checks']['reachable'])->toBeFalse();

    expect(fn () => app(SynologyMount::class)->assertAvailable())->toThrow(SynologyUnavailableException::class);
});

// ── Todo lo público va al NAS; el servidor solo es respaldo temporal ──

test('with the NAS up the original and all variants go to the NAS and nothing stays on the server', function () {
    $nas = FlakyDisk::install();
    activateSynology();

    $media = nasPhoto();

    expect($media->disk)->toBe('media_synology')
        ->and($media->variants()->count())->toBeGreaterThan(0)
        ->and($media->variants()->pluck('disk')->unique()->all())->toBe(['media_synology'])
        ->and(Storage::disk('media_local')->allFiles())->toBe([])
        ->and(collect($nas->files())->filter(fn ($f) => str_ends_with($f, '.part'))->all())->toBe([]);
});

test('with the NAS up a video and its poster go to the NAS', function () {
    FlakyDisk::install();
    activateSynology();
    fakeFfmpeg();

    $video = tinyMp4();
    $media = app(MediaUploadService::class)->ingest($video, 'Recorrido Obra.mp4', 'video/mp4', (int) filesize($video), 'sitio/general')->refresh();

    $poster = $media->variants()->where('type', 'poster')->sole();
    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->duration_seconds)->toBe(12)
        ->and($media->disk)->toBe('media_synology')
        ->and($poster->disk)->toBe('media_synology')
        ->and($poster->path)->toBe(preg_replace('/\.mp4$/', '-portada.jpg', $media->path))
        ->and(Storage::disk('media_local')->allFiles())->toBe([]);
    Storage::disk('media_synology')->assertExists([$media->path, $poster->path]);
});

test('pending files fall back to the server and are moved to the NAS with verification', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $nas->down = true;

    $media = nasPhoto();
    $paths = [$media->path, ...$media->variants()->pluck('path')->all()];
    expect($media->disk)->toBe('media_local')
        ->and($media->pending_sync_to)->toBe('media_synology');
    Storage::disk('media_local')->assertExists($paths);

    $nas->down = false;
    $this->artisan('media:sync-pending')->assertSuccessful();

    $media->refresh();
    expect($media->disk)->toBe('media_synology')
        ->and($media->pending_sync_to)->toBeNull()
        ->and(hash('xxh128', (string) Storage::disk('media_synology')->get($media->path)))->toBe($media->checksum)
        ->and(Storage::disk('media_local')->allFiles())->toBe([]);
    Storage::disk('media_synology')->assertExists($paths);
});

test('files that keep failing do not block newer pending files', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $nas->down = true;
    $old = nasPhoto('vieja.jpg');
    $new = nasPhoto('nueva.jpg');
    $old->forceFill(['sync_attempts' => 9])->save();
    $nas->down = false;

    $this->artisan('media:sync-pending', ['--limit' => 1])->assertSuccessful();

    expect($new->refresh()->disk)->toBe('media_synology')
        ->and($old->refresh()->disk)->toBe('media_local');
});

test('the fallback is refused when the server lacks free space and nothing is lost', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $nas->down = true;
    config(['media.storage.fallback_reserve_mb' => 1024 * 1024 * 1024]);

    expect(fn () => nasPhoto())->toThrow(RejectedUploadException::class, 'espacio');
    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('media_local')->allFiles())->toBe([]);
});

// ── Lectura y streaming de archivos históricos ────────────────────────

test('historical files on either disk are streamed with Range support', function (string $disk) {
    FlakyDisk::install();
    $media = Media::factory()->create(['disk' => $disk, 'path' => 'sitio/general/'.Str::uuid().'.jpg', 'mime_type' => 'image/jpeg']);
    Storage::disk($disk)->put($media->path, '0123456789');

    $this->get('/media/'.$media->path, ['Range' => 'bytes=2-5'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('Content-Length', '4');

    expect($this->get('/media/'.$media->path)->assertOk()->streamedContent())->toBe('0123456789');
})->with(['media_local', 'media_synology']);

test('with the NAS marked down reads go straight to the server copy', function () {
    $nas = FlakyDisk::install();
    $media = Media::factory()->create(['disk' => 'media_synology', 'mime_type' => 'image/jpeg']);
    Storage::disk('media_synology')->put($media->path, 'nas');
    Storage::disk('media_local')->put($media->path, 'servidor');
    app(MediaStorage::class)->markDown('media_synology', 'caído');
    $calls = $nas->calls;

    expect($this->get('/media/'.$media->path)->assertOk()->streamedContent())->toBe('servidor')
        ->and($nas->calls)->toBe($calls);
});

test('private documents always stay on media_private even with the NAS active', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $pdf = tempnam(sys_get_temp_dir(), 'mlh-pdf-');
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $media = app(MediaUploadService::class)->ingest($pdf, 'Contrato.pdf', 'application/pdf', (int) filesize($pdf), null, MediaVisibility::Private);

    expect($media->disk)->toBe('media_private')
        ->and($media->pending_sync_to)->toBeNull()
        ->and($nas->files())->toBe([]);
    Storage::disk('media_private')->assertExists($media->path);
});

// ── Procesamiento de imágenes y video ────────────────────────────────

test('image variants wait for the NAS instead of failing when the original is on it and it is down', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $media = nasPhoto();
    $media->variants()->delete();
    $nas->down = true;

    (new GenerateImageVariants($media->id))->handle(app(MediaStorage::class));

    expect($media->refresh()->variants()->count())->toBe(0)
        ->and(Storage::disk('media_local')->allFiles())->toBe([]);

    $nas->down = false;
    (new GenerateImageVariants($media->id))->handle(app(MediaStorage::class));
    expect($media->variants()->pluck('disk')->unique()->all())->toBe(['media_synology']);
});

test('a video without ffmpeg is ready, also when the NAS is down', function () {
    $nas = FlakyDisk::install();
    activateSynology();
    $video = tinyMp4();

    $media = app(MediaUploadService::class)->ingest($video, 'obra.mp4', 'video/mp4', (int) filesize($video), 'sitio/general')->refresh();
    expect($media->status)->toBe(MediaStatus::Ready)->and($media->disk)->toBe('media_synology');

    fakeFfmpeg();
    $nas->down = true;
    $media->forceFill(['status' => MediaStatus::Processing->value])->save();
    app()->call([new ProcessVideoMedia($media->id), 'handle']);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($media->variants()->count())->toBe(0);
});

// ── Rutas legibles ───────────────────────────────────────────────────

test('new uploads get readable paths by type, year and month', function () {
    FlakyDisk::install();
    activateSynology();
    fakeFfmpeg();
    $ym = now()->format('Y/m');

    $image = nasPhoto('Cocina Integral Ñandú.jpg');
    expect($image->path)->toMatch("#^sitio/general/imagenes/{$ym}/cocina-integral-nandu-[a-z0-9]{8}\\.jpg$#")
        ->and($image->filename)->toBe(basename($image->path));

    $base = substr($image->path, 0, -4);
    expect($image->variants()->pluck('path')->all())->toContain("{$base}-480.webp");

    $video = tinyMp4();
    $clip = app(MediaUploadService::class)->ingest($video, '???.mp4', 'video/mp4', (int) filesize($video), 'proyectos/casa-roble')->refresh();
    expect($clip->path)->toMatch("#^proyectos/casa-roble/videos/{$ym}/archivo-[a-z0-9]{8}\\.mp4$#")
        ->and($clip->variants()->where('type', 'poster')->value('path'))->toBe(substr($clip->path, 0, -4).'-portada.jpg');

    $long = nasPhoto(str_repeat('palabra ', 30).'.jpg');
    expect(strlen(pathinfo($long->filename, PATHINFO_FILENAME)))->toBeLessThanOrEqual(60 + 9);
});

test('replacing keeps the record but generates a new readable path', function () {
    FlakyDisk::install();
    activateSynology();
    $media = nasPhoto('sala.jpg');
    $before = $media->path;

    $file = new UploadedFile(WebFixtures::jpeg(900, 600), 'Sala Nueva.jpg', 'image/jpeg', null, true);
    $replaced = app(MediaUploadService::class)->replace($media, $file);

    expect($replaced->id)->toBe($media->id)
        ->and($replaced->path)->not->toBe($before)
        ->and($replaced->path)->toMatch('#^sitio/general/imagenes/\d{4}/\d{2}/sala-nueva-[a-z0-9]{8}\.jpg$#')
        ->and($replaced->disk)->toBe('media_synology');
});

test('storage admin page still requires settings.manage', function () {
    $this->actingAs(F::user(UserRole::Operations))->get('/admin/almacenamiento')->assertForbidden();
});
