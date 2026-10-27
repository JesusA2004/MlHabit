<?php

use App\Enums\MediaVariantType;
use App\Jobs\GenerateImageVariants;
use App\Models\MediaCollection;
use App\Models\MediaVariant;
use App\Models\User;
use App\Services\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('generates a webp optimized version and a thumbnail for an uploaded image', function () {
    Storage::fake('media_local');

    $root = MediaCollection::query()->create(['parent_id' => null, 'name' => 'MR. INSIGHT', 'slug' => 'mr-insight', 'path' => 'mr-insight']);
    MediaCollection::query()->create(['parent_id' => $root->id, 'name' => 'General', 'slug' => 'general', 'path' => 'mr-insight/general']);

    $this->actingAs(User::factory()->create());

    $media = app(MediaUploadService::class)->upload(UploadedFile::fake()->image('foto.jpg', 1200, 800));

    (new GenerateImageVariants($media->id))->handle();

    $webp = MediaVariant::query()->where('media_id', $media->id)->where('type', MediaVariantType::OptimizedWebp->value)->first();
    $thumb = MediaVariant::query()->where('media_id', $media->id)->where('type', MediaVariantType::Thumbnail->value)->first();

    expect($webp)->not->toBeNull();
    expect($thumb)->not->toBeNull();
    expect($thumb->width)->toBeLessThanOrEqual(480);

    Storage::disk('media_local')->assertExists($webp->path);
    Storage::disk('media_local')->assertExists($thumb->path);

    // El original nunca se modifica ni se elimina.
    Storage::disk('media_local')->assertExists($media->path);
});
