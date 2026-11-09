<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantType;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\FfmpegService;
use App\Services\Storage\MediaRelocator;
use App\Services\Storage\MediaStorage;
use App\Support\MediaPaths;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extrae duración y miniatura de un video recién subido. Si FFmpeg no está
 * instalado en el servidor, el video permanece utilizable (estado "ready")
 * simplemente sin miniatura ni duración automáticas.
 *
 * Funciona con el video en el servidor o en el NAS (disco local sobre el
 * montaje SMB): FFmpeg lee la ruta directa. Si el NAS no está disponible,
 * el video queda «ready» sin miniatura (no se toca el montaje caído).
 * La miniatura se guarda con respaldo (servidor si el NAS no responde).
 */
class ProcessVideoMedia implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $mediaId) {}

    public function handle(FfmpegService $ffmpeg, MediaStorage $storage): void
    {
        $lock = Cache::lock(MediaRelocator::lockKey($this->mediaId), 1800);
        if (! $lock->get()) {
            $this->release(60);

            return;
        }

        try {
            $this->process($ffmpeg, $storage);
        } finally {
            $lock->release();
        }
    }

    private function process(FfmpegService $ffmpeg, MediaStorage $storage): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            return;
        }

        if (! $ffmpeg->isAvailable()) {
            Log::info('ProcessVideoMedia: FFmpeg no disponible, se omite miniatura/duración.', ['media_id' => $media->id]);
            $media->update(['status' => MediaStatus::Ready->value]);

            return;
        }

        $temporaryVideo = null;
        $temporaryPoster = tempnam(sys_get_temp_dir(), 'mlh-poster-').'.jpg';

        try {
            $absoluteVideoPath = $this->localPath($media, $temporaryVideo, $storage);
            if ($absoluteVideoPath === null) {
                Log::warning('ProcessVideoMedia: no se pudo leer el video.', ['media_id' => $media->id]);
                $media->update(['status' => MediaStatus::Ready->value]);

                return;
            }

            $duration = $ffmpeg->durationSeconds($absoluteVideoPath);

            $posterPath = MediaPaths::posterPath($media->path);

            if ($ffmpeg->generateThumbnail($absoluteVideoPath, $temporaryPoster)) {
                $preferred = $media->pending_sync_to ?? $media->disk;
                $contents = (string) file_get_contents($temporaryPoster);

                try {
                    $disk = $storage->put($preferred, $posterPath, $contents);
                    if ($disk !== $preferred && $media->pending_sync_to === null) {
                        $storage->markPending($media, $preferred, $storage->downReason($preferred));
                    }

                    MediaVariant::query()->updateOrCreate(
                        ['media_id' => $media->id, 'type' => MediaVariantType::Poster->value],
                        ['disk' => $disk, 'path' => $posterPath, 'size' => strlen($contents) ?: null],
                    );
                } catch (Throwable $e) {
                    Log::warning('ProcessVideoMedia: no se pudo guardar la miniatura.', ['media_id' => $media->id, 'error' => $e->getMessage()]);
                }
            }

            $media->update([
                'duration_seconds' => $duration,
                'status' => MediaStatus::Ready->value,
            ]);
        } finally {
            if ($temporaryVideo !== null && is_file($temporaryVideo)) {
                @unlink($temporaryVideo);
            }
            if (is_file($temporaryPoster)) {
                @unlink($temporaryPoster);
            }
            $base = substr($temporaryPoster, 0, -4);
            if (is_file($base)) {
                @unlink($base);
            }
        }
    }

    /**
     * Ruta local del video: la del disco si es local; si no, un temporal.
     */
    private function localPath(Media $media, ?string &$temporary, MediaStorage $storage): ?string
    {
        // Antes de tocar la ruta se comprueba (con el guardián) dónde se puede
        // leer: un montaje caído nunca se toca directamente.
        $readable = $storage->readableDisk($media->disk, $media->path);
        if ($readable === null) {
            return null;
        }
        $disk = Storage::disk($readable);

        try {
            $direct = $disk->path($media->path);
            if (is_file($direct)) {
                return $direct;
            }
        } catch (Throwable) {
            // Discos remotos pueden no exponer ruta local.
        }

        try {
            $stream = $disk->readStream($media->path);
        } catch (Throwable) {
            return null;
        }
        if (! is_resource($stream)) {
            return null;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'mlh-video-') ?: null;
        if ($temporary === null) {
            fclose($stream);

            return null;
        }

        $out = fopen($temporary, 'wb');
        if ($out === false) {
            fclose($stream);

            return null;
        }
        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);

        return $temporary;
    }
}
