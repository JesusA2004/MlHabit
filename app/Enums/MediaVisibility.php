<?php

namespace App\Enums;

enum MediaVisibility: string
{
    /** Catálogo y sitio público: disco de medios público (/media). */
    case Public = 'public';
    /** Documentos, fotos de vivienda y evidencias: disco privado, acceso autorizado. */
    case Private = 'private';

    public function disk(string $activePublicDisk): string
    {
        return $this === self::Private ? 'media_private' : $activePublicDisk;
    }
}
