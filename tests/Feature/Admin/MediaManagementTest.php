<?php

use App\Enums\MediaStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessVideoMedia;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\User;
use App\Services\FfmpegService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

function mediaAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function mediaColaborador(): User
{
    return User::factory()->create(['role' => UserRole::Colaborador]);
}

beforeEach(function () {
    Storage::fake('media_local');
    Storage::fake('local');
    $root = MediaCollection::query()->create(['parent_id' => null, 'name' => 'MR. INSIGHT', 'slug' => 'mr-insight', 'path' => 'mr-insight']);
    MediaCollection::query()->create(['parent_id' => $root->id, 'name' => 'General', 'slug' => 'general', 'path' => 'mr-insight/general']);
});

test('guests cannot upload media', function () {
    $this->post('/admin/multimedia', ['file' => UploadedFile::fake()->image('foto.jpg')])
        ->assertRedirect('/login');
});

test('colaborador cannot upload media', function () {
    $this->actingAs(mediaColaborador())
        ->post('/admin/multimedia', ['file' => UploadedFile::fake()->image('foto.jpg')])
        ->assertForbidden();
});

test('admin can upload an image and it becomes ready immediately', function () {
    $response = $this->actingAs(mediaAdmin())->post('/admin/multimedia', [
        'file' => UploadedFile::fake()->image('foto.jpg', 800, 600),
    ]);

    $response->assertCreated();
    $media = Media::query()->first();

    expect($media)->not->toBeNull();
    expect($media->status)->toBe(MediaStatus::Ready);
    expect($media->width)->toBe(800);
    Storage::disk('media_local')->assertExists($media->path);
});

test('uploading a video dispatches processing and starts in processing status', function () {
    Bus::fake();

    $this->actingAs(mediaAdmin())->post('/admin/multimedia', [
        'file' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
    ])->assertCreated();

    $media = Media::query()->first();
    expect($media->status)->toBe(MediaStatus::Processing);

    Bus::assertDispatched(ProcessVideoMedia::class, fn ($job) => $job->mediaId === $media->id);
});

test('video processing degrades gracefully without ffmpeg configured', function () {
    config(['media.ffmpeg_path' => null, 'media.ffprobe_path' => null]);

    $media = Media::factory()->create(['type' => 'video', 'status' => MediaStatus::Processing->value]);

    (new ProcessVideoMedia($media->id))->handle(new FfmpegService);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready);
});

test('admin can create a destination collection', function () {
    $response = $this->actingAs(mediaAdmin())->post('/admin/multimedia-colecciones', [
        'parent_id' => MediaCollection::query()->where('slug', 'mr-insight')->first()->id,
        'name' => 'Negocios',
    ]);

    $response->assertCreated();
    expect(MediaCollection::query()->where('path', 'mr-insight/negocios')->exists())->toBeTrue();
});

test('chunked upload assembles a file across multiple requests', function () {
    $user = mediaAdmin();
    $content = str_repeat('a', 300_000);
    $chunkSize = 100_000;

    $start = $this->actingAs($user)->postJson('/admin/multimedia-sesiones', [
        'filename' => 'video-grande.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => strlen($content),
        'collection' => 'mr-insight/general',
    ]);
    $start->assertCreated();
    $uuid = $start->json('uuid');

    foreach (str_split($content, $chunkSize) as $index => $chunk) {
        $file = UploadedFile::fake()->createWithContent('chunk.part', $chunk);
        $this->actingAs($user)
            ->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", ['chunk' => $file, 'chunkIndex' => $index])
            ->assertOk();
    }

    $complete = $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/completar");
    $complete->assertCreated();

    $media = Media::query()->where('original_filename', 'video-grande.mp4')->first();
    expect($media)->not->toBeNull();
    expect($media->size)->toBe(strlen($content));
});

test('a chunk sent twice is applied only once', function () {
    $user = mediaAdmin();
    $content = str_repeat('b', 200_000);
    $chunkSize = 100_000;

    $start = $this->actingAs($user)->postJson('/admin/multimedia-sesiones', [
        'filename' => 'resumible.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => strlen($content),
        'collection' => 'mr-insight/general',
    ]);
    $uuid = $start->json('uuid');

    $chunks = str_split($content, $chunkSize);
    $firstChunk = UploadedFile::fake()->createWithContent('chunk.part', $chunks[0]);

    $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", ['chunk' => $firstChunk, 'chunkIndex' => 0])->assertOk();

    // Reintento de red: mismo fragmento, mismo índice.
    $retry = UploadedFile::fake()->createWithContent('chunk.part', $chunks[0]);
    $response = $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", ['chunk' => $retry, 'chunkIndex' => 0])->assertOk();

    expect($response->json('receivedSize'))->toBe(strlen($chunks[0]));
});

test('a chunk sent out of order is rejected', function () {
    $user = mediaAdmin();

    $start = $this->actingAs($user)->postJson('/admin/multimedia-sesiones', [
        'filename' => 'fuera-de-orden.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => 200_000,
        'collection' => 'mr-insight/general',
    ]);
    $uuid = $start->json('uuid');

    $chunk = UploadedFile::fake()->createWithContent('chunk.part', str_repeat('c', 50_000));
    $this->actingAs($user)
        ->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", ['chunk' => $chunk, 'chunkIndex' => 5])
        ->assertStatus(409);
});

test('completing an upload before all bytes arrive fails', function () {
    $user = mediaAdmin();

    $start = $this->actingAs($user)->postJson('/admin/multimedia-sesiones', [
        'filename' => 'incompleto.mp4',
        'mimeType' => 'video/mp4',
        'totalSize' => 200_000,
        'collection' => 'mr-insight/general',
    ]);
    $uuid = $start->json('uuid');

    $chunk = UploadedFile::fake()->createWithContent('chunk.part', str_repeat('d', 100_000));
    $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/fragmentos", ['chunk' => $chunk, 'chunkIndex' => 0])->assertOk();

    $this->actingAs($user)->post("/admin/multimedia-sesiones/{$uuid}/completar")->assertStatus(422);
});

test('media alt text can be updated and the file can be deleted', function () {
    $user = mediaAdmin();
    $media = Media::factory()->create();

    $this->actingAs($user)
        ->patchJson("/admin/multimedia/{$media->id}", ['alt_text' => 'Descripción accesible'])
        ->assertOk();

    expect($media->refresh()->alt_text)->toBe('Descripción accesible');

    $this->actingAs($user)->deleteJson("/admin/multimedia/{$media->id}")->assertOk();
    expect(Media::query()->find($media->id))->toBeNull();
});
