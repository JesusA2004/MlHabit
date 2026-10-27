<?php

namespace App\Http\Controllers\Public;

use App\Enums\MediaVisibility;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega pública (/media/…) de la biblioteca: imágenes, variantes y video
 * con soporte de HTTP Range (necesario en Safari/iPad).
 *
 * - Solo sirve rutas que existen como `path` de un Media o MediaVariant
 *   (defensa contra path traversal).
 * - Rechaza SIEMPRE archivos privados: esos solo salen por
 *   `archivos.show` tras autorizar.
 * - Los nombres de archivo son UUID (reemplazar un archivo genera otra
 *   ruta), así que se cachean como inmutables por un año.
 */
class MediaStreamController extends Controller
{
    private const CHUNK_SIZE = 1024 * 1024;

    public function __invoke(Request $request, string $path): Response|StreamedResponse
    {
        $resolved = $this->resolve($path);
        if ($resolved === null) {
            abort(404);
        }

        [$disk, $mimeType] = $resolved;
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            abort(404);
        }

        $size = (int) $storage->size($path);
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($mimeType === 'application/pdf') {
            $headers['Content-Disposition'] = 'inline';
        }

        if ($request->isMethod('HEAD')) {
            return response('', 200, [...$headers, 'Content-Length' => (string) $size]);
        }

        $rangeHeader = $request->header('Range');
        if ($rangeHeader === null) {
            return new StreamedResponse(fn () => $this->stream($storage, $path, 0, $size - 1), 200, [...$headers, 'Content-Length' => (string) $size]);
        }

        $range = $this->parseRange($rangeHeader, $size);
        if ($range === null) {
            return response('', 416, [...$headers, 'Content-Range' => "bytes */{$size}"]);
        }

        [$start, $end] = $range;

        return new StreamedResponse(fn () => $this->stream($storage, $path, $start, $end), 206, [
            ...$headers,
            'Content-Length' => (string) ($end - $start + 1),
            'Content-Range' => "bytes {$start}-{$end}/{$size}",
        ]);
    }

    private function stream(Filesystem $storage, string $path, int $start, int $end): void
    {
        $stream = $storage->readStream($path);
        if ($stream === null) {
            return;
        }
        if ($start > 0) {
            fseek($stream, $start);
        }

        $remaining = $end - $start + 1;
        while ($remaining > 0 && ! feof($stream)) {
            $read = (int) min(self::CHUNK_SIZE, $remaining);
            echo fread($stream, $read);
            $remaining -= $read;
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        fclose($stream);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function resolve(string $path): ?array
    {
        $media = Media::query()->where('path', $path)->first();
        if ($media !== null) {
            return $media->visibility === MediaVisibility::Public ? [$media->disk, $media->mime_type] : null;
        }

        $variant = MediaVariant::query()->with('media')->where('path', $path)->first();
        if ($variant !== null && $variant->media !== null && $variant->media->visibility === MediaVisibility::Public) {
            return [$variant->disk, $this->guessMimeFromExtension($variant->path)];
        }

        return null;
    }

    private function guessMimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseRange(string $header, int $size): ?array
    {
        if ($size <= 0 || ! str_starts_with($header, 'bytes=')) {
            return null;
        }

        $first = explode(',', substr($header, 6))[0];
        [$startPart, $endPart] = array_pad(explode('-', $first, 2), 2, '');

        if ($startPart === '' && $endPart === '') {
            return null;
        }

        if ($startPart === '') {
            $start = max(0, $size - (int) $endPart);
            $end = $size - 1;
        } else {
            $start = (int) $startPart;
            $end = $endPart === '' ? $size - 1 : min((int) $endPart, $size - 1);
        }

        if ($start < 0 || $end < $start || $start >= $size) {
            return null;
        }

        return [$start, $end];
    }
}
