<?php

namespace App\Jobs;

use App\Enums\MediaType;
use App\Enums\MediaVariantType;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\Storage\MediaRelocator;
use App\Services\Storage\MediaStorage;
use App\Services\Storage\SynologyUnavailableException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
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
 *
 * Funciona igual con el original en el servidor o en el NAS. Si el NAS no
 * responde al guardar una variante, esta queda en el servidor y el Media se
 * marca pendiente de sincronizar. Un candado por archivo evita chocar con
 * un traslado servidor ⇄ NAS en curso.
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    /** Errores reales permitidos; las esperas por NAS caído no cuentan. */
    public int $maxExceptions = 2;

    public int $timeout = 300;

    public function __construct(public int $mediaId) {}

    /** Mientras el NAS esté caído se reintenta (cada 5 min) hasta 12 horas. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    private function remoteAvailable(Media $media): bool
    {
        try {
            Storage::disk($media->disk)->exists($media->path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function handle(?MediaStorage $storage = null): void
    {
        $storage ??= app(MediaStorage::class);
        $lock = Cache::lock(MediaRelocator::lockKey($this->mediaId), 900);
        if (! $lock->get()) {
            // Se está trasladando este archivo: reintentar en un momento.
            $this->release(60);

            return;
        }

        try {
            $this->generate($storage);
        } finally {
            $lock->release();
        }
    }

    private function generate(MediaStorage $storage): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || $media->type !== MediaType::Image) {
            return;
        }

        // Los GIF pueden ser animados: se conservan tal cual.
        if ($media->mime_type === 'image/gif') {
            return;
        }

        // El original puede estar en el NAS: si no está disponible (y no hay
        // copia en el servidor) se reintenta más tarde sin bloquear la cola.
        $readable = $storage->readableDisk($media->disk, $media->path);
        if ($readable === null && $storage->isRemote($media->disk) && ! $this->remoteAvailable($media)) {
            Log::info('GenerateImageVariants: el NAS no está disponible; se reintentará.', ['media_id' => $media->id]);
            $this->release(300);

            return;
        }

        try {
            $source = $readable !== null ? Storage::disk($readable)->get($media->path) : null;
        } catch (SynologyUnavailableException) {
            $this->release(300);

            return;
        }
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
            $generated[] = $this->store($storage, $media, MediaVariantType::webp($width), "{$prefix}-{$width}.webp", $scaled, 'webp');

            if ($avif) {
                try {
                    $generated[] = $this->store($storage, $media, MediaVariantType::avif($width), "{$prefix}-{$width}.avif", $scaled, 'avif');
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
            try {
                Storage::disk($variant->disk)->delete($variant->path);
            } catch (Throwable $e) {
                Log::info('GenerateImageVariants: no se pudo borrar una variante vieja.', ['media_id' => $media->id, 'error' => $e->getMessage()]);
            }
            $variant->delete();
        }

        $media->forceFill([
            'width' => $originalWidth,
            'height' => $originalHeight,
            'metadata' => [...(is_array($media->metadata) ? $media->metadata : []), 'color' => $this->dominantColor($original)],
        ])->save();
    }

    private function store(MediaStorage $storage, Media $media, MediaVariantType $type, string $path, ImageInterface $image, string $format): string
    {
        $encoded = $format === 'avif'
            ? (string) $image->toAvif(quality: (int) config('media.avif_quality', 55))
            : (string) $image->toWebp(quality: (int) config('media.webp_quality', 80));

        // Las variantes van al disco que DEBE tener el archivo (el NAS si
        // está pendiente de sincronizar); si no responde, al servidor.
        $preferred = $media->pending_sync_to ?? $media->disk;
        $disk = $storage->put($preferred, $path, $encoded);
        if ($disk !== $preferred && $media->pending_sync_to === null) {
            $storage->markPending($media, $preferred, $storage->downReason($preferred));
        }

        $existing = MediaVariant::query()->where('media_id', $media->id)->where('type', $type->value)->first();
        if ($existing !== null && ($existing->path !== $path || $existing->disk !== $disk)) {
            try {
                Storage::disk($existing->disk)->delete($existing->path);
            } catch (Throwable) {
                // El NAS puede no responder; la variante nueva ya quedó guardada.
            }
        }

        MediaVariant::query()->updateOrCreate(
            ['media_id' => $media->id, 'type' => $type->value],
            ['disk' => $disk, 'path' => $path, 'width' => $image->width(), 'height' => $image->height(), 'size' => strlen($encoded)],
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
