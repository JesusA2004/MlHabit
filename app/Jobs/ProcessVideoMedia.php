<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantType;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Services\FfmpegService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Extrae duración y miniatura de un video recién subido. Si FFmpeg no está
 * instalado en el servidor, el video permanece utilizable (estado "ready")
 * simplemente sin miniatura ni duración automáticas.
 */
class ProcessVideoMedia implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId) {}

    public function handle(FfmpegService $ffmpeg): void
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

        $disk = Storage::disk($media->disk);
        $absoluteVideoPath = $disk->path($media->path);

        $duration = $ffmpeg->durationSeconds($absoluteVideoPath);

        $posterFilename = Str::uuid()->toString().'.jpg';
        $posterPath = dirname($media->path).'/'.$posterFilename;
        $absolutePosterPath = $disk->path($posterPath);

        $generated = $ffmpeg->generateThumbnail($absoluteVideoPath, $absolutePosterPath);

        if ($generated) {
            MediaVariant::query()->create([
                'media_id' => $media->id,
                'type' => MediaVariantType::Poster->value,
                'disk' => $media->disk,
                'path' => $posterPath,
                'size' => filesize($absolutePosterPath) ?: null,
            ]);
        }

        $media->update([
            'duration_seconds' => $duration,
            'status' => MediaStatus::Ready->value,
        ]);
    }
}
