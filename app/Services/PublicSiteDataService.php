<?php

namespace App\Services;

use App\Services\Web\PublicSite;

/**
 * Adaptador de compatibilidad: bootstrap/app.php lo usa para la página
 * 404. La fuente de verdad es App\Services\Web\PublicSite.
 */
class PublicSiteDataService
{
    public function __construct(private readonly PublicSite $site) {}

    /**
     * @return array{site: array<string, mixed>}
     */
    public function base(): array
    {
        return ['site' => $this->site->site()];
    }
}
