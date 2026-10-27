<?php

namespace App\Studio;

use App\Models\Page;
use App\Models\SiteSetting;

/**
 * Documentos iniciales de las páginas del sistema. Se construyen a partir
 * del contenido que ya existe en el CMS (textos de /admin/inicio, páginas,
 * redes) para que la migración a Studio no pierda nada: el sitio arranca
 * mostrando lo mismo que antes, pero ya editable visualmente.
 *
 * Los textos de páginas del CMS se enlazan por referencia (bind) en vez
 * de copiarse, para no duplicar contenido.
 */
class StudioDefaults
{
    public const SYSTEM_PAGES = [
        'home' => 'Inicio',
        'mi-historia' => 'Mi historia',
        'mi-proceso' => 'Mi proceso',
        'redes' => 'Redes',
        'tip' => 'Plantilla de Tips',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forPage(string $key): array
    {
        $sections = match ($key) {
            'home' => $this->home(),
            'mi-historia' => $this->historia(),
            'mi-proceso' => $this->proceso(),
            'redes' => $this->redes(),
            'tip' => $this->tip(),
            default => $this->blank(),
        };

        return ['schema' => StudioSchema::version(), 'sections' => $sections];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function home(): array
    {
        $hero = SiteSetting::get('hero', []);
        $empieza = SiteSetting::get('empieza_aqui', []);
        $tips = SiteSetting::get('tips_section', []);
        $historia = SiteSetting::get('historia_preview', []);
        $proceso = SiteSetting::get('proceso_preview', []);

        $buttons = [];
        if (($hero['primary_cta_label'] ?? '') !== '') {
            $buttons[] = ['label' => $hero['primary_cta_label'], 'link' => ['type' => 'social', 'value' => 'instagram', 'newTab' => true], 'variant' => 'primary'];
        }
        if (($hero['secondary_cta_label'] ?? '') !== '') {
            $buttons[] = ['label' => $hero['secondary_cta_label'], 'link' => ['type' => 'page', 'value' => '/tips', 'newTab' => false], 'variant' => 'secondary'];
        }
        if (($hero['tertiary_link_label'] ?? '') !== '') {
            $buttons[] = ['label' => $hero['tertiary_link_label'], 'link' => ['type' => 'social', 'value' => 'tiktok', 'newTab' => true], 'variant' => 'link'];
        }

        $titleLines = array_filter((array) ($hero['title_lines'] ?? []), fn ($l) => is_string($l) && $l !== '');
        $historiaPhoto = $historia['photo_media_id'] ?? null;

        return [
            $this->section('hero', ['layout' => 'editorial', 'spacing' => 'editorial', 'height' => 'tall'], [
                'content' => array_values(array_filter([
                    ($hero['eyebrow'] ?? '') !== '' ? $this->el('eyebrow', ['text' => $hero['eyebrow']]) : null,
                    $this->el('heading', ['text' => $titleLines !== [] ? implode("\n", $titleLines) : 'NEGOCIOS. HÁBITOS. VIDA.', 'level' => 'h1', 'size' => 'xl'], motion: $this->motion('slide-up')),
                    $buttons !== [] ? $this->el('buttons', ['items' => array_slice($buttons, 0, 3)], motion: $this->motion('fade', 200)) : null,
                ])),
                'media' => [
                    $this->el('image', ['media' => $hero['photo_media_id'] ?? null, 'alt' => 'Zuriel Ávila', 'priority' => true], ['base' => ['aspectRatio' => '4/5', 'radius' => 16]], $this->motion('reveal', 100)),
                ],
            ], motion: null),
            $this->section('categories', [
                'layout' => 'cards',
                'eyebrow' => 'EMPIEZA AQUÍ',
                'title' => $empieza['title'] ?? 'CONOCE MI PERSPECTIVA.',
                'divider' => true,
            ], motion: $this->motion('slide-up')),
            $this->section('posts', [
                'layout' => 'magazine',
                'eyebrow' => 'TIPS',
                'title' => $tips['title'] ?? 'IDEAS QUE DEJAN HUELLA.',
                'source' => 'latest',
                'limit' => 6,
                'linkLabel' => 'Ver todos los tips',
                'divider' => true,
            ], motion: $this->motion('slide-up')),
            $this->section('columns', ['layout' => 'two-wide-right', 'spacing' => 'editorial', 'tone' => 'ink-soft'], [
                'col1' => [
                    $historiaPhoto
                        ? $this->el('image', ['media' => $historiaPhoto, 'alt' => ''], ['base' => ['aspectRatio' => '4/5', 'radius' => 16, 'frame' => 'offset']], $this->motion('reveal'))
                        : $this->el('image', ['bind' => 'cover', 'bindPage' => 'mi-historia', 'alt' => ''], ['base' => ['aspectRatio' => '4/5', 'radius' => 16, 'frame' => 'offset']], $this->motion('reveal')),
                ],
                'col2' => [
                    $this->el('eyebrow', ['text' => 'MI HISTORIA']),
                    $this->el('heading', ['text' => $historia['title'] ?? 'MI HISTORIA', 'size' => 'lg']),
                    $this->el('text', ['bind' => 'intro', 'bindPage' => 'mi-historia', 'size' => 'lead']),
                    $this->el('buttons', ['items' => [['label' => 'Leer la historia completa', 'link' => ['type' => 'page', 'value' => '/mi-historia', 'newTab' => false], 'variant' => 'secondary']]]),
                ],
            ], ['base' => ['verticalAlign' => 'center', 'gap' => 64]], $this->motion('slide-up')),
            $this->section('cta', ['layout' => 'band', 'tone' => 'gold', 'spacing' => 'balanced'], [
                'content' => [
                    $this->el('eyebrow', ['text' => 'MI PROCESO']),
                    $this->el('heading', ['text' => $proceso['title'] ?? '365 DÍAS DE CUIDAR DE MÍ.', 'size' => 'lg']),
                    $this->el('buttons', ['items' => [['label' => 'Ver el proceso', 'link' => ['type' => 'page', 'value' => '/mi-proceso', 'newTab' => false], 'variant' => 'secondary']]]),
                ],
                'media' => [],
            ], motion: $this->motion('fade')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historia(): array
    {
        $page = Page::query()->where('key', 'mi-historia')->first();

        return [
            $this->section('hero', ['layout' => $page?->cover_media_id ? 'editorial' : 'centered', 'spacing' => 'editorial'], [
                'content' => [
                    $this->el('eyebrow', ['text' => 'MI HISTORIA']),
                    $this->el('heading', ['bind' => 'title', 'level' => 'h1', 'size' => 'xl'], ['base' => ['textTransform' => 'uppercase']], $this->motion('slide-up')),
                    $this->el('text', ['bind' => 'intro', 'size' => 'lead'], motion: $this->motion('fade', 150)),
                ],
                'media' => [
                    $this->el('image', ['bind' => 'cover', 'alt' => '', 'priority' => true], ['base' => ['aspectRatio' => '4/5', 'radius' => 16]], $this->motion('reveal')),
                ],
            ]),
            $this->section('columns', ['layout' => 'one', 'width' => 'narrow', 'spacing' => 'balanced', 'divider' => true], [
                'col1' => [$this->el('page-body', ['page' => ''])],
                'col2' => [],
                'col3' => [],
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function proceso(): array
    {
        return [
            $this->section('hero', ['layout' => 'centered', 'spacing' => 'editorial'], [
                'content' => [
                    $this->el('eyebrow', ['text' => 'MI PROCESO']),
                    $this->el('heading', ['bind' => 'title', 'level' => 'h1', 'size' => 'xl'], ['base' => ['textTransform' => 'uppercase']], $this->motion('slide-up')),
                    $this->el('text', ['bind' => 'intro', 'size' => 'lead'], ['base' => ['maxWidth' => 680, 'align' => 'center', 'textAlign' => 'center']]),
                ],
                'media' => [],
            ]),
            $this->section('timeline', ['layout' => 'alternating', 'source' => 'entries', 'page' => 'mi-proceso', 'divider' => true], motion: $this->motion('slide-up')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function redes(): array
    {
        return [
            $this->section('hero', ['layout' => 'centered', 'spacing' => 'balanced'], [
                'content' => [
                    $this->el('eyebrow', ['text' => 'REDES']),
                    $this->el('heading', ['text' => 'SÍGUEME DONDE PREFIERAS.', 'level' => 'h1', 'size' => 'xl'], motion: $this->motion('slide-up')),
                ],
                'media' => [],
            ]),
            $this->section('social', ['layout' => 'cards', 'eyebrow' => '', 'title' => ''], motion: $this->motion('slide-up')),
        ];
    }

    /**
     * Plantilla dramática por defecto para renderizar cada publicación (tip).
     * Sus bloques se enlazan a la publicación actual: portada, título e intro
     * por bind; el cuerpo Tiptap por page-body; y la metainformación, galería
     * y publicaciones relacionadas por los bloques específicos de tips.
     *
     * @return list<array<string, mixed>>
     */
    private function tip(): array
    {
        return [
            $this->section('hero', ['layout' => 'cinematic', 'height' => 'tall', 'overlay' => 0.55, 'spacing' => 'editorial', 'tone' => 'ink'], [
                'media' => [
                    $this->el('image', ['bind' => 'cover', 'alt' => '', 'priority' => true], ['base' => ['aspectRatio' => '16/9']], $this->motion('reveal')),
                ],
                'content' => [
                    $this->el('post-meta', ['showCategory' => true, 'showDate' => true, 'showReadingTime' => true], motion: $this->motion('fade')),
                    $this->el('heading', ['bind' => 'title', 'level' => 'h1', 'size' => 'xl'], motion: $this->motion('slide-up')),
                    $this->el('text', ['bind' => 'intro', 'size' => 'lead'], motion: $this->motion('fade', 150)),
                ],
            ]),
            $this->section('columns', ['layout' => 'one', 'width' => 'narrow', 'spacing' => 'editorial', 'tone' => 'ink'], [
                'col1' => [
                    $this->el('page-body', ['page' => '']),
                    $this->el('divider', ['variant' => 'short']),
                    $this->el('share', ['label' => 'Compartir']),
                ],
            ]),
            $this->section('gallery', ['layout' => 'bento', 'source' => 'post', 'spacing' => 'balanced', 'tone' => 'ink', 'eyebrow' => '', 'title' => '']),
            $this->section('posts', ['layout' => 'cards', 'source' => 'related', 'eyebrow' => 'SIGUE LEYENDO', 'title' => 'TAMBIÉN TE PUEDE INTERESAR', 'limit' => 3, 'showExcerpt' => true, 'showDate' => false, 'linkLabel' => '', 'spacing' => 'balanced', 'tone' => 'ink-soft'], motion: $this->motion('slide-up')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blank(): array
    {
        return [
            $this->section('hero', ['layout' => 'centered', 'spacing' => 'editorial'], [
                'content' => [
                    $this->el('eyebrow', ['text' => 'NUEVA PÁGINA']),
                    $this->el('heading', ['text' => 'Un titular con intención.', 'level' => 'h1', 'size' => 'xl']),
                    $this->el('text', ['text' => 'Describe aquí de qué trata esta página.', 'size' => 'lead']),
                ],
                'media' => [],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, list<array<string, mixed>>>  $slots
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>|null  $motion
     * @return array<string, mixed>
     */
    private function section(string $type, array $props, array $slots = [], array $style = [], ?array $motion = null): array
    {
        $def = StudioSchema::section($type) ?? [];
        foreach (array_keys($def['slots'] ?? []) as $slot) {
            $slots[$slot] ??= [];
        }

        return [
            'id' => self::id(),
            'type' => $type,
            'v' => (int) ($def['version'] ?? 1),
            'props' => $props,
            'style' => $style,
            'motion' => $motion,
            'hidden' => false,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>|null  $motion
     * @return array<string, mixed>
     */
    private function el(string $type, array $props, array $style = [], ?array $motion = null): array
    {
        return [
            'id' => self::id(),
            'type' => $type,
            'v' => (int) (StudioSchema::element($type)['version'] ?? 1),
            'props' => $props,
            'style' => $style,
            'motion' => $motion,
            'hidden' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function motion(string $preset, int $delay = 0): array
    {
        return ['preset' => $preset, 'duration' => 800, 'delay' => $delay, 'distance' => 24, 'trigger' => 'scroll', 'mobile' => 'reduce', 'once' => true];
    }

    public static function id(): string
    {
        return 'n_'.bin2hex(random_bytes(4));
    }
}
