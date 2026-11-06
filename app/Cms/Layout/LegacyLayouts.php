<?php

namespace App\Cms\Layout;

use App\Enums\SiteSectionType;

/**
 * Convierte las secciones estructuradas de la portada (contenido tipado) en
 * lienzos del editor visual con el diseño curado vigente (portada v3,
 * noviembre de 2026): textos como bloques editables y el cuerpo de cada
 * sección como bloque dinámico. También sirve de plantilla al agregar una
 * sección nueva de ese tipo.
 *
 * Movimiento: antetítulos que se descubren (`wipe-right`), títulos palabra
 * por palabra (`split`), entradillas que se enfocan (`blur-up`), tarjetas
 * que se elevan (`rise`). El lienzo anterior quedó congelado en
 * `Legacy\HomeV1Layouts` (lo usa `HomeRedesign` para comparar).
 */
final class LegacyLayouts
{
    /**
     * @param  array<string, mixed>  $content
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function for(SiteSectionType $type, array $content): array
    {
        $content = $type->schema()->normalize($content);

        return match ($type) {
            SiteSectionType::Hero => self::hero($content),
            SiteSectionType::Categories => self::categories($content),
            SiteSectionType::FeaturedProjects => self::featuredProjects($content),
            SiteSectionType::Process => self::process($content),
            SiteSectionType::Benefits => self::benefits($content),
            SiteSectionType::PaymentOptions => self::paymentOptions($content),
            SiteSectionType::HomeVisit => self::homeVisit($content),
            SiteSectionType::Coverage => self::coverage($content),
            SiteSectionType::Testimonials => self::testimonials($content),
            SiteSectionType::Faq => self::faq($content),
            SiteSectionType::FinalCta => self::finalCta($content),
            SiteSectionType::Canvas => Blocks::layout(['minHeight' => 'md'], []),
        };
    }

    /**
     * Antetítulo + título + texto de apoyo apilados en una columna.
     *
     * @param  array<string, mixed>  $c
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public static function intro(array $c, int $x, int $y, int $w, string $textKey = 'subtitle', ?int $titleRows = null, string $tone = 'light', string $align = 'start'): array
    {
        // Alto del título según su largo (~4.6 caracteres por columna en
        // tamaño L, ~2.5 filas por renglón): sin huecos bajo títulos cortos.
        $titleRows ??= max(3, (int) ceil(ceil(mb_strlen(self::str($c, 'title')) / max(10, $w * 4.6)) * 2.5));
        $blocks = [];
        $delay = 0;
        $style = ['align' => $align];
        if (self::str($c, 'eyebrow') !== '') {
            $blocks[] = Blocks::make('badge', $x, $y, $w, 1, ['text' => self::str($c, 'eyebrow'), 'variant' => 'eyebrow', 'color' => $tone === 'dark' ? 'oak' : 'oak-ink'], $style, ['preset' => 'wipe-right']);
            $y += 2;
            $delay += 80;
        }
        $blocks[] = Blocks::make('heading', $x, $y, $w, $titleRows, ['text' => self::str($c, 'title'), 'level' => 'h2', 'size' => 'lg'], $style, ['preset' => 'split', 'delay' => $delay]);
        $y += $titleRows;
        $delay += 160;
        if (self::str($c, $textKey) !== '') {
            // ~10 caracteres por columna en tamaño de entradilla (32 px por línea).
            $lines = (int) ceil(mb_strlen(self::str($c, $textKey)) / max(20, $w * 10));
            $rows = max(2, (int) ceil($lines * 4 / 3));
            $blocks[] = Blocks::make('text', $x, $y + 1, $w, $rows, ['html' => Blocks::paragraphs(self::str($c, $textKey)), 'size' => 'lead', 'color' => 'muted'], $style, ['preset' => 'blur-up', 'delay' => $delay]);
            $y += $rows + 1;
        }

        return [$blocks, $y];
    }

    /** @param array<string, mixed> $c */
    private static function str(array $c, string $key): string
    {
        return is_string($c[$key] ?? null) ? trim($c[$key]) : '';
    }

    /**
     * @param  array<string, mixed>  $c
     * @param  array<string, mixed>  $motion
     * @return array<string, mixed>
     */
    private static function dynamic(string $type, array $c, int $x, int $y, int $w, int $h, array $motion = ['preset' => 'fade-up', 'delay' => 120]): array
    {
        return Blocks::make($type, $x, $y, $w, $h, DynamicBlocks::propsFrom($type, $c), motion: $motion);
    }

    /**
     * Portada a sangre: titular enorme palabra por palabra sobre el verde
     * del logotipo con luz cálida (o la foto elegida), polvo de luz
     * (SectionDecor) y la escena ilustrada si no hay fotografía.
     *
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function hero(array $c): array
    {
        $media = is_int($c['media_id'] ?? null) ? $c['media_id'] : null;
        $blocks = [];
        $y = 0;
        $w = $media === null ? 7 : 8;

        if (self::str($c, 'eyebrow') !== '') {
            $blocks[] = Blocks::make('badge', 0, 0, 6, 2, ['text' => self::str($c, 'eyebrow'), 'variant' => 'glass'], motion: ['preset' => 'fade-in']);
            $y = 3;
        }
        $blocks[] = Blocks::make('heading', 0, $y, $w, 13, ['text' => self::str($c, 'title'), 'level' => 'h1', 'size' => 'display'], motion: ['preset' => 'split', 'delay' => 120]);
        $y += 14;
        if (self::str($c, 'subtitle') !== '') {
            $blocks[] = Blocks::make('text', 0, $y, 6, 5, ['html' => Blocks::paragraphs(self::str($c, 'subtitle')), 'size' => 'lead', 'color' => 'auto'], ['opacity' => 85], ['preset' => 'blur-up', 'delay' => 560]);
            $y += 6;
        }
        $blocks[] = Blocks::make('button', 0, $y, 3, 2, ['label' => self::str($c, 'primary_cta_label') ?: 'Agenda tu asesoría', 'href' => self::str($c, 'primary_cta_href') ?: '/solicitar', 'variant' => 'light'], motion: ['preset' => 'fade-up', 'delay' => 720]);
        if (self::str($c, 'secondary_cta_label') !== '' && self::str($c, 'secondary_cta_href') !== '') {
            $blocks[] = Blocks::make('button', 3, $y, 3, 2, ['label' => self::str($c, 'secondary_cta_label'), 'href' => self::str($c, 'secondary_cta_href'), 'variant' => 'outline-light', 'icon' => false], motion: ['preset' => 'fade-up', 'delay' => 800]);
        }
        $y += 3;
        if (self::str($c, 'note') !== '') {
            $blocks[] = Blocks::make('text', 0, $y, 6, 2, ['html' => Blocks::paragraphs(self::str($c, 'note')), 'size' => 'sm'], ['opacity' => 75], ['preset' => 'fade-in', 'delay' => 900]);
            $y += 2;
        }

        if ($media === null) {
            $blocks[] = Blocks::make('shape', 7, 0, 5, max(26, $y), ['kind' => 'interior', 'color' => 'primary'], ['align' => 'end'], ['preset' => 'zoom-in', 'delay' => 260]);
        }

        return Blocks::layout([
            'name' => 'Portada',
            'background' => $media !== null
                ? ['kind' => 'image', 'mediaId' => $media, 'overlay' => 65, 'overlayStyle' => 'left', 'color' => 'espresso']
                : ['kind' => 'gradient', 'gradient' => 'forest-night', 'color' => 'espresso'],
            'padding' => 'hero',
            'minHeight' => 'screen',
            'verticalAlign' => 'center',
            'tone' => 'dark',
            'overlayHeader' => true,
            'journey' => 'inspirate',
        ], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function categories(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 7);
        if (self::str($c, 'cta_label') !== '') {
            $blocks[] = Blocks::make('button', 9, max(2, $y - 2), 3, 2, ['label' => self::str($c, 'cta_label'), 'href' => '/catalogo', 'variant' => 'link'], ['align' => 'end'], ['preset' => 'fade-in', 'delay' => 200]);
        }
        $blocks[] = self::dynamic('categories', $c, 0, $y + 2, 12, 30, ['preset' => 'fade-in', 'delay' => 0]);

        return Blocks::layout(['name' => 'Categorías', 'journey' => 'espacio'], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function featuredProjects(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 7);
        if (self::str($c, 'cta_label') !== '') {
            $blocks[] = Blocks::make('button', 8, max(2, $y - 2), 4, 2, ['label' => self::str($c, 'cta_label'), 'href' => '/inspiracion', 'variant' => 'link'], ['align' => 'end'], ['preset' => 'fade-in', 'delay' => 200]);
        }
        $blocks[] = self::dynamic('featured_projects', $c, 0, $y + 2, 12, 28, ['preset' => 'fade-in', 'delay' => 0]);

        return Blocks::layout(['name' => 'Proyectos destacados', 'journey' => 'inspirate', 'background' => ['kind' => 'color', 'color' => 'stone-soft']], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function process(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 5);
        $blocks[] = Blocks::make('button', 0, $y + 1, 4, 2, ['label' => 'Agenda tu asesoría', 'href' => '/solicitar', 'variant' => 'primary'], motion: ['preset' => 'fade-up', 'delay' => 320]);
        $steps = is_array($c['steps'] ?? null) ? count($c['steps']) : 7;
        $blocks[] = self::dynamic('process_steps', $c, 6, 0, 6, max(8, $steps * 5), ['preset' => 'fade-in', 'delay' => 0]);

        return Blocks::layout(['name' => 'Cómo funciona', 'anchor' => 'como-funciona', 'journey' => 'proceso'], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function benefits(array $c): array
    {
        $media = is_int($c['media_id'] ?? null) ? $c['media_id'] : null;
        $blocks = $media !== null
            ? [Blocks::make('image', 0, 0, 5, 25, ['mediaId' => $media, 'alt' => ''], ['radius' => '2xl'], ['preset' => 'wipe-up'])]
            : [
                // Sin foto: muro de listones de roble (como el del logotipo)
                // con el muestrario de acabados encima.
                Blocks::make('shape', 0, 0, 5, 25, ['kind' => 'slats', 'color' => 'primary'], ['radius' => '2xl'], ['preset' => 'wipe-up'], z: 0),
                Blocks::make('shape', 2, 13, 4, 13, ['kind' => 'swatches', 'color' => 'white'], [], ['preset' => 'tilt-in', 'delay' => 300], ['mobile'], 1),
            ];
        [$intro, $y] = self::intro($c, 6, 1, 6);
        $items = is_array($c['items'] ?? null) ? count($c['items']) : 4;

        return Blocks::layout(['name' => 'Beneficios', 'verticalAlign' => 'center', 'journey' => 'proceso'], [
            ...$blocks,
            ...$intro,
            self::dynamic('benefits_list', $c, 6, $y + 1, 6, max(8, (int) ceil($items / 2) * 7), ['preset' => 'rise', 'delay' => 200]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function paymentOptions(array $c): array
    {
        [$blocks] = self::intro($c, 0, 0, 5, 'body');
        $items = is_array($c['items'] ?? null) ? count($c['items']) : 3;
        $h = $items * 5 + 5;
        $blocks[] = self::dynamic('payment_options', $c, 6, 2, 6, $h, ['preset' => 'fade-up', 'delay' => 160]);
        if (self::str($c, 'cta_label') !== '' && self::str($c, 'cta_href') !== '') {
            $blocks[] = Blocks::make('button', 6, $h + 3, 5, 2, ['label' => self::str($c, 'cta_label'), 'href' => self::str($c, 'cta_href'), 'variant' => 'outline', 'icon' => false], motion: ['delay' => 200]);
        }

        return Blocks::layout(['name' => 'Formas de pago', 'anchor' => 'pagos', 'journey' => 'pagos', 'background' => ['kind' => 'color', 'color' => 'linen']], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function homeVisit(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 6, 'body', null, 'dark');
        $points = array_values(array_filter(array_map(
            fn ($p) => is_array($p) && is_string($p['text'] ?? null) ? ['text' => trim($p['text'])] : null,
            is_array($c['points'] ?? null) ? $c['points'] : [],
        )));
        if ($points !== []) {
            $blocks[] = Blocks::make('list', 0, $y + 1, 6, count($points) * 2 + 1, ['items' => $points, 'marker' => 'check'], motion: ['preset' => 'fade-up', 'delay' => 260]);
            $y += count($points) * 2 + 2;
        }
        if (self::str($c, 'cta_label') !== '' && self::str($c, 'cta_href') !== '') {
            $blocks[] = Blocks::make('button', 0, $y + 1, 3, 2, ['label' => self::str($c, 'cta_label'), 'href' => self::str($c, 'cta_href'), 'variant' => 'oak'], motion: ['preset' => 'fade-up', 'delay' => 320]);
        }
        $blocks[] = self::dynamic('home_visit', $c, 6, 0, 6, max(20, $y + 3), ['preset' => 'rise', 'delay' => 160]);

        return Blocks::layout([
            'name' => 'Visita técnica',
            'anchor' => 'visita',
            'journey' => 'visita',
            'background' => ['kind' => 'gradient', 'gradient' => 'espresso-depth', 'color' => 'espresso'],
            'tone' => 'dark',
            'verticalAlign' => 'center',
        ], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function coverage(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 1, 6, 'body');
        $blocks[] = Blocks::make('shape', 9, 0, 3, 11, ['kind' => 'rings', 'color' => 'primary'], ['align' => 'end', 'opacity' => 12], ['preset' => 'fade-in'], ['mobile'], 0);
        $blocks[] = self::dynamic('coverage_checker', $c, 7, 3, 5, max(8, $y - 4), ['preset' => 'fade-up', 'delay' => 200]);

        return Blocks::layout([
            'name' => 'Cobertura',
            'journey' => 'visita',
            'background' => ['kind' => 'color', 'color' => 'primary-soft'],
            'surface' => 'panel',
            'verticalAlign' => 'center',
        ], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function testimonials(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 7);
        $blocks[] = self::dynamic('testimonials', $c, 0, $y + 2, 12, 14, ['preset' => 'fade-in', 'delay' => 0]);

        return Blocks::layout(['name' => 'Testimonios', 'background' => ['kind' => 'color', 'color' => 'stone-soft']], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function faq(array $c): array
    {
        [$blocks, $y] = self::intro($c, 0, 0, 4);
        if (self::str($c, 'cta_label') !== '') {
            $blocks[] = Blocks::make('button', 0, $y + 1, 4, 2, ['label' => self::str($c, 'cta_label'), 'href' => '/preguntas-frecuentes', 'variant' => 'link'], motion: ['delay' => 200]);
        }
        $limit = is_int($c['limit'] ?? null) ? $c['limit'] : 8;
        $blocks[] = self::dynamic('faq', $c, 5, 0, 7, max(10, min($limit, 6) * 3), ['preset' => 'fade-up', 'delay' => 120]);

        return Blocks::layout(['name' => 'Preguntas frecuentes'], $blocks);
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private static function finalCta(array $c): array
    {
        return Blocks::layout(['name' => 'Llamada final', 'padding' => 'md', 'journey' => 'agenda'], [
            self::dynamic('final_cta', $c, 0, 0, 12, 18, ['preset' => 'wipe-up', 'delay' => 0]),
        ]);
    }
}
