<?php

use App\Models\Media;
use App\Models\MediaCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('media_local');
});

function streamedMedia(string $content = 'contenido-de-prueba-0123456789'): Media
{
    $collection = MediaCollection::query()->firstOrCreate(
        ['path' => 'mr-insight/general'],
        ['parent_id' => null, 'name' => 'General', 'slug' => 'general'],
    );
    $path = 'mr-insight/general/'.Str::uuid().'.jpg';
    Storage::disk('media_local')->put($path, $content);

    return Media::factory()->create([
        'media_collection_id' => $collection->id,
        'disk' => 'media_local',
        'path' => $path,
        'mime_type' => 'image/jpeg',
        'size' => strlen($content),
    ]);
}

test('serves the full file with a 200 when no range is requested', function () {
    $media = streamedMedia('hola mundo');

    $response = $this->get("/media/{$media->path}");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
    $response->assertHeader('Accept-Ranges', 'bytes');
    expect($response->streamedContent())->toBe('hola mundo');
});

test('serves a partial 206 response for a byte range', function () {
    $media = streamedMedia('0123456789');

    $response = $this->withHeaders(['Range' => 'bytes=2-5'])->get("/media/{$media->path}");

    $response->assertStatus(206);
    $response->assertHeader('Content-Range', 'bytes 2-5/10');
    expect($response->streamedContent())->toBe('2345');
});

test('serves a suffix range (last N bytes)', function () {
    $media = streamedMedia('0123456789');

    $response = $this->withHeaders(['Range' => 'bytes=-3'])->get("/media/{$media->path}");

    $response->assertStatus(206);
    expect($response->streamedContent())->toBe('789');
});

test('returns 416 for a range beyond the file size', function () {
    $media = streamedMedia('0123456789');

    $response = $this->withHeaders(['Range' => 'bytes=100-200'])->get("/media/{$media->path}");

    $response->assertStatus(416);
    $response->assertHeader('Content-Range', 'bytes */10');
});

test('answers HEAD requests with no body and the correct content length', function () {
    $media = streamedMedia('0123456789');

    $response = $this->head("/media/{$media->path}");

    $response->assertOk();
    $response->assertHeader('Content-Length', '10');
});

test('an unregistered path never reaches the filesystem and returns 404', function () {
    $this->get('/media/mr-insight/general/no-existe.jpg')->assertNotFound();
});

test('a path traversal attempt is rejected because it matches no media record', function () {
    streamedMedia();

    $this->get('/media/../../../../etc/passwd')->assertNotFound();
});
