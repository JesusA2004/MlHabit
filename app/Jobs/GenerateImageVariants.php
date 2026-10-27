<?php

namespace App\Jobs;

use App\Enums\MediaType;
use App\Enums\MediaVariantType;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Genera las variantes responsivas de una imagen: WebP a 480 (miniatura),
 * 960, 1600 y 2400 px, solo hasta el ancho real del original (no se
 * amplía), más AVIF con los mismos anchos cuando GD lo soporta. Guarda
 * dimensiones y un color dominante para el marcador de carga. El original
 * nunca se modifica.
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public int $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || $media->type !== MediaType::Image) {
            return;
        }

        // Los GIF pueden ser animados: se conservan tal cual.
        if ($media->mime_type === 'image/gif') {
            return;
        }

        $disk = Storage::disk($media->disk);
        $source = $disk->get($media->path);
        if ($source === null) {
            Log::warning('GenerateImageVariants: no se encontró el original.', ['media_id' => $media->id]);

            return;
        }

        $manager = ImageManager::gd();

        try {
            $original = $manager->read($source);
        } catch (Throwable $e) {
            Log::warning('GenerateImageVariants: imagen ilegible.', ['media_id' => $media->id, 'error' => $e->getMessage()]);

            return;
        }

        $originalWidth = $original->width();
        $originalHeight = $original->height();

        $directory = dirname($media->path);
        $prefix = ($directory === '.' ? '' : $directory.'/').pathinfo($media->path, PATHINFO_FILENAME);
        $avif = (bool) config('media.avif', true) && function_exists('imageavif');

        $generated = [];
        $previous = 0;
        foreach (MediaVariantType::WIDTHS as $width) {
            // Un ancho solo se genera si el original supera el escalón anterior.
            if ($width !== MediaVariantType::WIDTHS[0] && $originalWidth <= $previous) {
                break;
            }
            $previous = $width;

            $scaled = (clone $original)->scaleDown(width: $width);
            $generated[] = $this->store($media, MediaVariantType::webp($width), "{$prefix}-{$width}.webp", $scaled, 'webp');

            if ($avif) {
                try {
                    $generated[] = $this->store($media, MediaVariantType::avif($width), "{$prefix}-{$width}.avif", $scaled, 'avif');
                } catch (Throwable $e) {
                    Log::info('GenerateImageVariants: AVIF no disponible.', ['media_id' => $media->id, 'error' => $e->getMessage()]);
                    $avif = false;
                }
            }
        }

        // Retira variantes responsivas que ya no aplican (p. ej. tras reemplazar).
        $stale = $media->variants()->whereNotIn('type', $generated)->get()
            ->filter(fn (MediaVariant $v) => MediaVariantType::tryFrom($v->type)?->isResponsive() || in_array($v->type, [MediaVariantType::OptimizedWebp->value, MediaVariantType::OptimizedAvif->value], true));
        foreach ($stale as $variant) {
            Storage::disk($variant->disk)->delete($variant->path);
            $variant->delete();
        }

        $media->forceFill([
            'width' => $originalWidth,
            'height' => $originalHeight,
            'metadata' => [...(is_array($media->metadata) ? $media->metadata : []), 'color' => $this->dominantColor($original)],
        ])->save();
    }

    private function store(Media $media, MediaVariantType $type, string $path, ImageInterface $image, string $format): string
    {
        $encoded = $format === 'avif'
            ? (string) $image->toAvif(quality: (int) config('media.avif_quality', 55))
            : (string) $image->toWebp(quality: (int) config('media.webp_quality', 80));

        Storage::disk($media->disk)->put($path, $encoded);

        $existing = MediaVariant::query()->where('media_id', $media->id)->where('type', $type->value)->first();
        if ($existing !== null && $existing->path !== $path) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => $type->value],
            ['disk' => $media->disk, 'path' => $path, 'width' => $image->width(), 'height' => $image->height(), 'size' => strlen($encoded)],
        );

        return $type->value;
    }

    private function dominantColor(ImageInterface $image): ?string
    {
        try {
            $color = (clone $image)->resize(1, 1)->pickColor(0, 0);

            return $color->toHex('#');
        } catch (Throwable) {
            return null;
        }
    }
}
