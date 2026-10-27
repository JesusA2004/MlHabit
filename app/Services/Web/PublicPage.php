<?php

namespace App\Services\Web;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renderiza páginas públicas con las props compartidas (`site`) y el SEO.
 */
class PublicPage
{
    public function __construct(private readonly PublicSite $site) {}

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $seo
     */
    public function render(string $component, array $props, array $seo, bool $overlay = false): Response
    {
        return Inertia::render($component, [
            'site' => $this->site->site(),
            'overlay' => $overlay,
            'seo' => $seo,
            ...$props,
        ]);
    }
}
