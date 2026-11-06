<?php

namespace App\Cms\Layout;

use App\Enums\SiteSectionType;
use Illuminate\Support\Str;

/**
 * Portada por defecto (v3, rediseño de noviembre de 2026): el orden, los
 * textos sugeridos y los lienzos de una instalación nueva, y lo que publica
 * `HomeRedesign` sobre una portada sin personalizar.
 *
 * Recorrido: portada cinematográfica → cifras de confianza → líneas de
 * servicio → por qué nosotros → cómo funciona → materiales → ideas y
 * proyectos → visita a domicilio → cobertura → precios de asesoría y
 * visita → formas de pago → testimonios → preguntas → llamada final.
 *
 * Las listas vivas (categorías, proyectos, testimonios, preguntas) llevan
 * «Mostrar contenido de ejemplo» activado: con el catálogo vacío la portada
 * no tiene huecos. Nada de cifras, precios ni testimonios inventados: los
 * precios salen de la configuración y las cifras son del oficio (editables).
 */
final class HomeBlueprint
{
    /**
     * Secciones listas para un snapshot del editor (`key, type, isVisible,
     * content, layout`). Las claves se generan nuevas en cada llamada.
     *
     * @return list<array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>}>
     */
    public static function sections(): array
    {
        $curated = function (SiteSectionType $type, array $overrides = []): array {
            $content = $type->schema()->normalize([...$type->schema()->defaults(), ...$overrides]);

            return self::section($type, $content, LegacyLayouts::for($type, $content));
        };

        return [
            $curated(SiteSectionType::Hero, [
                'eyebrow' => 'Cocinas · Clósets · Interiores a la medida',
                'title' => 'Muebles a tu medida, hechos para quedarse',
                'primary_cta_label' => 'Agenda tu asesoría',
                'primary_cta_href' => '/solicitar',
                'secondary_cta_label' => 'Agenda una visita',
                'secondary_cta_href' => '/agendar-visita',
                'note' => 'Asesoría por videollamada · Visita técnica con medición en tu domicilio',
            ]),
            self::section(SiteSectionType::Canvas, [], self::trust()),
            $curated(SiteSectionType::Categories, ['show_example' => true]),
            $curated(SiteSectionType::Benefits),
            $curated(SiteSectionType::Process),
            self::section(SiteSectionType::Canvas, [], self::materials()),
            $curated(SiteSectionType::FeaturedProjects, [
                'eyebrow' => 'Inspiración',
                'title' => 'Ideas que se vuelven espacios',
                'show_example' => true,
            ]),
            $curated(SiteSectionType::HomeVisit),
            $curated(SiteSectionType::Coverage),
            self::section(SiteSectionType::Canvas, [], self::pricing()),
            $curated(SiteSectionType::PaymentOptions),
            $curated(SiteSectionType::Testimonials, [
                'eyebrow' => 'Confianza',
                'title' => 'Lo que puedes esperar de nosotros',
                'show_example' => true,
            ]),
            $curated(SiteSectionType::Faq, ['show_example' => true, 'limit' => 6]),
            $curated(SiteSectionType::FinalCta, [
                'eyebrow' => 'Tu espacio, a tu medida',
                'title' => 'Empecemos a diseñar tu espacio',
                'primary_cta_label' => 'Agenda tu asesoría',
                'primary_cta_href' => '/solicitar',
                'secondary_cta_label' => 'Agenda una visita',
                'secondary_cta_href' => '/agendar-visita',
            ]),
        ];
    }

    /**
     * Banda de cifras del oficio (cuentan al revelarse). Son hechos del
     * servicio, no estadísticas: el equipo puede cambiarlas por años,
     * proyectos o garantía reales desde el editor.
     *
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function trust(): array
    {
        $stats = [
            ['100 %', 'a la medida: nada de muebles de línea'],
            ['5', 'líneas: cocinas, clósets, recámaras, TV y zapateras'],
            ['7', 'etapas con seguimiento, de la idea a la instalación'],
            ['1', 'solo equipo que diseña, fabrica e instala'],
        ];

        $blocks = [];
        foreach ($stats as $i => [$value, $label]) {
            $blocks[] = Blocks::make('stat', $i * 3, 0, 3, 6, ['value' => $value, 'label' => $label, 'color' => 'auto'], motion: ['preset' => 'fade-up', 'delay' => $i * 90]);
        }

        return Blocks::layout([
            'name' => 'Cifras',
            'background' => ['kind' => 'color', 'color' => 'surface'],
            'padding' => 'md',
        ], $blocks);
    }

    /**
     * Banda oscura con la marquesina de materiales a sangre.
     *
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function materials(): array
    {
        return Blocks::layout([
            'name' => 'Materiales',
            'background' => ['kind' => 'color', 'color' => 'espresso'],
            'tone' => 'dark',
            'padding' => 'md',
            'journey' => 'espacio',
        ], [
            Blocks::make('badge', 2, 0, 8, 1, ['text' => 'Materiales y acabados', 'variant' => 'eyebrow', 'color' => 'oak'], ['align' => 'center'], ['preset' => 'wipe-right']),
            Blocks::make('heading', 1, 2, 10, 4, ['text' => 'Maderas, lacas y herrajes que se sienten al tacto', 'level' => 'h2', 'size' => 'md'], ['align' => 'center'], ['preset' => 'split', 'delay' => 80]),
            Blocks::make('shape', 0, 7, 12, 7, ['kind' => 'materials', 'color' => 'espresso'], motion: ['preset' => 'fade-in', 'delay' => 200]),
        ]);
    }

    /**
     * Precios de asesoría y visita (montos de la configuración vigente).
     *
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function pricing(): array
    {
        $center = ['align' => 'center'];

        return Blocks::layout([
            'name' => 'Precios',
            'journey' => 'pagos',
        ], [
            Blocks::make('badge', 3, 0, 6, 1, ['text' => 'Precios claros', 'variant' => 'eyebrow', 'color' => 'oak-ink'], $center, ['preset' => 'wipe-right']),
            Blocks::make('heading', 2, 2, 8, 4, ['text' => 'Empieza con una asesoría o una visita', 'level' => 'h2', 'size' => 'lg'], $center, ['preset' => 'split', 'delay' => 80]),
            Blocks::make('text', 3, 7, 6, 3, ['html' => Blocks::paragraphs('Agendas y pagas en línea. Sabes cuánto cuesta antes de confirmar, sin sorpresas.'), 'size' => 'lead', 'color' => 'muted'], $center, ['preset' => 'blur-up', 'delay' => 240]),
            Blocks::make('service_pricing', 1, 11, 10, 22, DynamicBlocks::schema('service_pricing')->defaults(), motion: ['preset' => 'fade-in']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $layout
     * @return array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>}
     */
    private static function section(SiteSectionType $type, array $content, array $layout): array
    {
        return [
            'key' => (string) Str::uuid(),
            'type' => $type->value,
            'isVisible' => true,
            'content' => $content,
            'layout' => $layout,
        ];
    }
}
