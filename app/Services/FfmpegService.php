<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Envuelve los binarios ffmpeg/ffprobe configurados por el administrador
 * del servidor (FFMPEG_BINARY_PATH / FFPROBE_BINARY_PATH). Si no están
 * instalados, el pipeline de video debe degradarse con elegancia: seguir
 * aceptando la subida, pero sin miniatura ni duración automáticas.
 */
class FfmpegService
{
    public function isAvailable(): bool
    {
        return $this->ffmpegPath() !== null && $this->ffprobePath() !== null;
    }

    public function ffmpegPath(): ?string
    {
        return $this->resolveBinary(config('media.ffmpeg_path'));
    }

    public function ffprobePath(): ?string
    {
        return $this->resolveBinary(config('media.ffprobe_path'));
    }

    public function durationSeconds(string $absolutePath): ?int
    {
        $ffprobe = $this->ffprobePath();
        if ($ffprobe === null) {
            return null;
        }

        $process = new Process([
            $ffprobe, '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $absolutePath,
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return (int) round((float) trim($process->getOutput()));
    }

    public function generateThumbnail(string $absoluteVideoPath, string $absoluteOutputPath, int $atSecond = 1): bool
    {
        $ffmpeg = $this->ffmpegPath();
        if ($ffmpeg === null) {
            return false;
        }

        $process = new Process([
            $ffmpeg, '-y', '-ss', (string) $atSecond, '-i', $absoluteVideoPath,
            '-frames:v', '1', '-q:v', '2', $absoluteOutputPath,
        ]);
        $process->run();

        return $process->isSuccessful() && file_exists($absoluteOutputPath);
    }

    private function resolveBinary(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return is_executable($path) ? $path : null;
    }
}
