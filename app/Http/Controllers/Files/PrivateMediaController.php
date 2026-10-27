<?php

namespace App\Http\Controllers\Files;

use App\Enums\MediaStatus;
use App\Enums\MediaVariantType;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /archivos/{media:uuid}[?variante=…] (`archivos.show`): entrega
 * archivos privados tras autorizar (ver PrivateMediaAccess). Nunca se
 * cachean en proxies ni navegador compartido.
 */
class PrivateMediaController extends Controller
{
    public function __invoke(Request $request, Media $media): StreamedResponse
    {
        Gate::authorize('view', $media);
        abort_if($media->status !== MediaStatus::Ready, 404);

        $disk = $media->disk;
        $path = $media->path;
        $mime = $media->mime_type;

        $variantType = $request->query('variante');
        if (is_string($variantType) && $variantType !== '') {
            $type = MediaVariantType::tryFrom($variantType);
            abort_if($type === null, 404);
            /** @var MediaVariant|null $variant */
            $variant = $media->variants()->where('type', $type->value)->first();
            abort_if($variant === null, 404);
            $disk = $variant->disk;
            $path = $variant->path;
            $mime = match ($type->format()) {
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                default => $type === MediaVariantType::Poster ? 'image/jpeg' : 'application/octet-stream',
            };
        }

        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf' || str_starts_with($mime, 'video/');
        $filename = Str::ascii(pathinfo($media->original_filename, PATHINFO_FILENAME)) ?: 'archivo';
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename).'.'.pathinfo($path, PATHINFO_EXTENSION);

        $size = (int) $storage->size($path);

        return new StreamedResponse(function () use ($storage, $path) {
            $stream = $storage->readStream($path);
            if ($stream === null) {
                return;
            }
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
