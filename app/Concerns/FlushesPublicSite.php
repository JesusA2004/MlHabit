<?php

namespace App\Concerns;

use App\Services\Web\PublicSite;

/**
 * Invalida la caché del sitio público cuando cambia contenido editable
 * (catálogo, CMS, páginas legales, redes sociales…).
 */
trait FlushesPublicSite
{
    public static function bootFlushesPublicSite(): void
    {
        static::saved(fn () => PublicSite::flush());
        static::deleted(fn () => PublicSite::flush());
    }
}
