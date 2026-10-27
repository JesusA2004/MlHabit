<?php

namespace App\Jobs;

use App\Enums\MediaVariantType;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Genera una miniatura y una versión WebP optimizada de una imagen recién
 * subida. Corre en cola para no bloquear la petición de subida con trabajo
 * de procesamiento de imagen. El archivo original nunca se modifica ni se
 * elimina.
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $absoluteOriginal = $disk->path($media->path);

        $manager = ImageManager::gd();
        $directory = dirname($media->path);
        $basename = pathinfo($media->path, PATHINFO_FILENAME);

        $image = $manager->read($absoluteOriginal);

        $webpPath = "{$directory}/{$basename}.webp";
        $webpEncoded = $image->toWebp(quality: 82);
        $disk->put($webpPath, (string) $webpEncoded);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => MediaVariantType::OptimizedWebp->value],
            ['disk' => $media->disk, 'path' => $webpPath, 'width' => $image->width(), 'height' => $image->height(), 'size' => strlen((string) $webpEncoded)]
        );

        $thumbnail = $manager->read($absoluteOriginal)->scaleDown(width: 480);
        $thumbPath = "{$directory}/{$basename}-thumb.webp";
        $thumbEncoded = $thumbnail->toWebp(quality: 75);
        $disk->put($thumbPath, (string) $thumbEncoded);

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => MediaVariantType::Thumbnail->value],
            ['disk' => $media->disk, 'path' => $thumbPath, 'width' => $thumbnail->width(), 'height' => $thumbnail->height(), 'size' => strlen((string) $thumbEncoded)]
        );
    }
}
