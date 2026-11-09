<?php

namespace App\Support;

use App\Enums\MediaType;
use Illuminate\Support\Str;

/**
 * Rutas legibles de la biblioteca para subidas NUEVAS:
 *
 *   {colección}/{tipo}/{AAAA}/{MM}/{slug-del-nombre}-{id8}.{ext}
 *
 * p. ej. `sitio/general/imagenes/2026/11/cocina-integral-a1b2c3d4.jpg`.
 * El id corto aleatorio mantiene cada ruta única e inmutable (la caché
 * pública es de un año): reemplazar un archivo genera otra ruta. Las
 * variantes van en la misma carpeta (`…-a1b2c3d4-960.webp`) y el póster de
 * video como `…-a1b2c3d4-portada.jpg`. Las rutas históricas (UUID) no se
 * tocan: cada registro guarda la suya.
 */
final class MediaPaths
{
    private const SLUG_MAX = 60;

    /**
     * @return array{path: string, filename: string}
     */
    public static function forUpload(string $collectionPath, MediaType $type, string $originalFilename, string $extension): array
    {
        $slug = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
        $slug = trim(Str::limit($slug, self::SLUG_MAX, ''), '-');
        if ($slug === '') {
            $slug = 'archivo';
        }

        $filename = $slug.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $directory = implode('/', array_filter([
            trim($collectionPath, '/'),
            self::typeFolder($type),
            now()->format('Y'),
            now()->format('m'),
        ], fn (string $segment) => $segment !== ''));

        return ['path' => $directory.'/'.$filename, 'filename' => $filename];
    }

    public static function typeFolder(MediaType $type): string
    {
        return match ($type) {
            MediaType::Image => 'imagenes',
            MediaType::Video => 'videos',
            MediaType::Document => 'documentos',
            MediaType::Other => 'otros',
        };
    }

    /** Ruta sin extensión: base de variantes y póster. */
    public static function base(string $mediaPath): string
    {
        $directory = dirname($mediaPath);

        return ($directory === '.' ? '' : $directory.'/').pathinfo($mediaPath, PATHINFO_FILENAME);
    }

    public static function posterPath(string $mediaPath): string
    {
        return self::base($mediaPath).'-portada.jpg';
    }
}
