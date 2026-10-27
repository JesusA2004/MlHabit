<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega pública de archivos de la biblioteca multimedia (imágenes y
 * video) con soporte real de peticiones HTTP Range: necesario para que
 * Safari/iPad puedan pausar, adelantar y retroceder sin descargar el video
 * completo.
 *
 * Nunca resuelve una ruta de archivo "a mano": solo sirve rutas que ya
 * existen como `path` de un registro Media o MediaVariant. Esto es a la vez
 * la defensa contra path traversal (una ruta con "../" simplemente no
 * coincide con ningún registro) y la manera de no revelar nunca el disco o
 * las credenciales reales del proveedor de almacenamiento activo — local
 * hoy, Synology por SFTP más adelante, sin cambiar este controlador.
 */
class MediaStreamController extends Controller
{
    private const CHUNK_SIZE = 1024 * 1024; // 1 MB por lectura, nunca el archivo completo en memoria.

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
            'Cache-Control' => 'private, max-age=86400',
        ];

        if ($request->isMethod('HEAD')) {
            return response('', 200, [...$headers, 'Content-Length' => (string) $size]);
        }

        $rangeHeader = $request->header('Range');

        if ($rangeHeader === null) {
            return new StreamedResponse(
                fn () => $this->stream($storage, $path, 0, $size - 1),
                200,
                [...$headers, 'Content-Length' => (string) $size],
            );
        }

        $range = $this->parseRange($rangeHeader, $size);

        if ($range === null) {
            return response('', 416, [...$headers, 'Content-Range' => "bytes */{$size}"]);
        }

        [$start, $end] = $range;
        $length = $end - $start + 1;

        return new StreamedResponse(
            fn () => $this->stream($storage, $path, $start, $end),
            206,
            [
                ...$headers,
                'Content-Length' => (string) $length,
                'Content-Range' => "bytes {$start}-{$end}/{$size}",
            ],
        );
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
            return [$media->disk, $media->mime_type];
        }

        $variant = MediaVariant::query()->where('path', $path)->first();
        if ($variant !== null) {
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
     * Soporta "bytes=start-end", "bytes=start-" y "bytes=-suffixLength".
     * Ignora rangos múltiples (solo toma el primero): suficiente para el
     * patrón real de reproductores de video, que piden un rango a la vez.
     *
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
            $suffixLength = (int) $endPart;
            $start = max(0, $size - $suffixLength);
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
