<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Web\OgImage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * GET /og/{tipo}/{slug}.png — imagen de vista previa (1200×630) para
 * WhatsApp, Facebook, LinkedIn, X, iMessage, Slack… Solo contenido
 * publicado; se genera una vez y se sirve desde disco.
 */
class OgImageController extends Controller
{
    public function __invoke(OgImage $og, string $type, string $slug): BinaryFileResponse
    {
        $spec = $og->resolve($type, $slug);
        abort_if($spec === null, 404);

        $path = $og->ensure($spec);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            // La URL publicada lleva ?v=<hash>; si cambia el contenido cambia la URL.
            'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
