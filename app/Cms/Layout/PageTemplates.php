<?php

namespace App\Cms\Layout;

use App\Enums\SiteSectionType;
use Illuminate\Support\Str;

/**
 * Plantillas de inicio para las páginas propias del sitio. Cada una arma
 * secciones con los mismos bloques del editor visual (encabezado,
 * texto + imagen, galería, video, secciones curadas), así una página nueva
 * ya se ve como ML Habitat. Textos de ejemplo neutrales y sin cifras ni
 * promesas inventadas: el equipo los reemplaza en el editor.
 */
final class PageTemplates
{
    /**
     * Catálogo para el selector «Nueva página» (sin las secciones).
     *
     * @return list<array{id: string, label: string, description: string, icon: string, sections: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            ['id' => 'blank', 'label' => 'En blanco con encabezado', 'description' => 'Un encabezado con el título de la página y un lienzo libre para acomodar lo que quieras.', 'icon' => 'square-dashed', 'sections' => ['Encabezado', 'Lienzo en blanco']],
            ['id' => 'service', 'label' => 'Página de servicio', 'description' => 'Presenta un servicio o una línea: encabezado con foto, beneficios, proceso, preguntas y llamada final.', 'icon' => 'layout-panel-left', 'sections' => ['Encabezado con foto', 'Beneficios', 'Proceso', 'Preguntas', 'Llamada final']],
            ['id' => 'gallery', 'label' => 'Galería / proyecto', 'description' => 'Muestra un proyecto terminado: encabezado, galería de fotos, la historia del proyecto y llamada final.', 'icon' => 'layout-grid', 'sections' => ['Encabezado', 'Galería', 'Texto + imagen', 'Llamada final']],
            ['id' => 'about', 'label' => 'Nosotros', 'description' => 'Cuenta quiénes son: encabezado con foto, historia, forma de trabajar, testimonios y llamada final.', 'icon' => 'users-round', 'sections' => ['Encabezado con foto', 'Nuestra historia', 'Cómo trabajamos', 'Testimonios', 'Llamada final']],
            ['id' => 'video_landing', 'label' => 'Landing con video', 'description' => 'Una página de campaña: portada oscura con video de YouTube o Vimeo, beneficios, invitación a solicitar y llamada final.', 'icon' => 'clapperboard', 'sections' => ['Portada con video', 'Beneficios', 'Invitación a solicitar', 'Llamada final']],
        ];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_column(self::catalog(), 'id');
    }

    /**
     * Secciones de la plantilla, listas para el borrador del editor.
     *
     * @return list<array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>}>
     */
    public static function sections(string $id, string $title): array
    {
        $title = trim($title) !== '' ? trim($title) : 'Nueva página';

        return match ($id) {
            'service' => [
                self::canvas(self::headerWithImage($title, 'Hecho a la medida', 'Cuenta en dos líneas qué incluye este servicio y para quién es.')),
                self::curated(SiteSectionType::Benefits),
                self::curated(SiteSectionType::Process),
                self::curated(SiteSectionType::Faq),
                self::curated(SiteSectionType::FinalCta),
            ],
            'gallery' => [
                self::canvas(self::header($title, 'Proyecto', 'Describe el espacio, la necesidad del cliente y lo que logramos juntos.')),
                self::canvas(self::gallery()),
                self::canvas(self::story('La historia del proyecto', '<p>Cuéntalo en pocas líneas: cómo era el espacio, qué pidió la familia y qué materiales y acabados elegimos.</p><p>Agrega detalles reales: medidas, herrajes o el tiempo de instalación.</p>')),
                self::curated(SiteSectionType::FinalCta),
            ],
            'about' => [
                self::canvas(self::headerWithImage($title, 'Nosotros', 'Diseñamos, fabricamos e instalamos muebles pensados para cada espacio.')),
                self::canvas(self::story('Nuestra historia', '<p>Cuenta cómo empezó el taller, qué los mueve y qué cuidan en cada proyecto.</p><p>Habla del equipo: diseñadores, carpinteros e instaladores.</p>')),
                self::canvas(self::values()),
                self::curated(SiteSectionType::Testimonials),
                self::curated(SiteSectionType::FinalCta),
            ],
            'video_landing' => [
                self::canvas(self::videoHero($title)),
                self::curated(SiteSectionType::Benefits),
                self::canvas(Blocks::layout(['name' => 'Invitación a solicitar', 'padding' => 'md'], [
                    Blocks::make('lead_form_cta', 0, 0, 12, 7),
                ])),
                self::curated(SiteSectionType::FinalCta),
            ],
            default => [
                self::canvas(self::header($title, 'ML Habitat', 'Escribe aquí una entradilla breve que explique de qué trata esta página.')),
                self::canvas(Blocks::layout(['name' => 'Lienzo en blanco', 'minHeight' => 'md'], [])),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>}
     */
    private static function canvas(array $layout): array
    {
        return ['key' => (string) Str::uuid(), 'type' => SiteSectionType::Canvas->value, 'isVisible' => true, 'content' => [], 'layout' => $layout];
    }

    /** @return array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>} */
    private static function curated(SiteSectionType $type): array
    {
        $content = $type->schema()->defaults();

        return ['key' => (string) Str::uuid(), 'type' => $type->value, 'isVisible' => true, 'content' => $content, 'layout' => LegacyLayouts::for($type, $content)];
    }

    /**
     * Encabezado de página: antetítulo, título principal (único H1),
     * entradilla y botón sobre el degradado lino-piedra.
     *
     * @return array<string, mixed>
     */
    private static function header(string $title, string $eyebrow, string $lead): array
    {
        return Blocks::layout([
            'name' => 'Encabezado',
            'background' => ['kind' => 'gradient', 'gradient' => 'linen-stone'],
            'padding' => 'xl',
        ], [
            Blocks::make('badge', 0, 0, 6, 1, ['text' => $eyebrow, 'variant' => 'eyebrow', 'color' => 'oak-ink'], motion: ['preset' => 'fade-in']),
            Blocks::make('heading', 0, 2, 8, 6, ['text' => Str::limit($title, 200, ''), 'level' => 'h1', 'size' => 'lg'], motion: ['preset' => 'mask-reveal', 'delay' => 80]),
            Blocks::make('text', 0, 9, 6, 3, ['html' => Blocks::paragraphs($lead), 'size' => 'lead', 'color' => 'muted'], motion: ['delay' => 160]),
            Blocks::make('button', 0, 13, 3, 2, ['label' => 'Solicita una asesoría', 'href' => '/solicitar', 'variant' => 'primary'], motion: ['delay' => 240]),
            Blocks::make('shape', 9, 1, 3, 12, ['kind' => 'swatches', 'color' => 'white'], ['align' => 'end'], ['preset' => 'fade-in', 'delay' => 200], ['mobile']),
        ]);
    }

    /** @return array<string, mixed> */
    private static function headerWithImage(string $title, string $eyebrow, string $lead): array
    {
        return Blocks::layout([
            'name' => 'Encabezado con foto',
            'background' => ['kind' => 'gradient', 'gradient' => 'linen-stone'],
            'padding' => 'xl',
            'verticalAlign' => 'center',
        ], [
            Blocks::make('badge', 0, 2, 6, 1, ['text' => $eyebrow, 'variant' => 'eyebrow', 'color' => 'oak-ink'], motion: ['preset' => 'fade-in']),
            Blocks::make('heading', 0, 4, 6, 7, ['text' => Str::limit($title, 200, ''), 'level' => 'h1', 'size' => 'lg'], motion: ['preset' => 'mask-reveal', 'delay' => 80]),
            Blocks::make('text', 0, 12, 5, 4, ['html' => Blocks::paragraphs($lead), 'size' => 'lead', 'color' => 'muted'], motion: ['delay' => 160]),
            Blocks::make('button', 0, 17, 3, 2, ['label' => 'Solicita una asesoría', 'href' => '/solicitar', 'variant' => 'primary'], motion: ['delay' => 240]),
            Blocks::make('button', 3, 17, 3, 2, ['label' => 'Ver catálogo', 'href' => '/catalogo', 'variant' => 'link', 'icon' => true], motion: ['delay' => 300]),
            Blocks::make('image', 7, 0, 5, 22, [], ['radius' => '2xl'], ['preset' => 'zoom-in']),
        ]);
    }

    /** @return array<string, mixed> */
    private static function gallery(): array
    {
        return Blocks::layout(['name' => 'Galería', 'padding' => 'lg'], [
            Blocks::make('heading', 0, 0, 7, 3, ['text' => 'Así quedó', 'level' => 'h2', 'size' => 'md']),
            Blocks::make('gallery', 0, 4, 12, 24, ['layout' => 'mosaic', 'columns' => 3], motion: ['preset' => 'fade-in', 'delay' => 80]),
        ]);
    }

    /**
     * Texto + imagen para una historia o descripción larga.
     *
     * @return array<string, mixed>
     */
    private static function story(string $title, string $html): array
    {
        return Blocks::layout(['name' => $title, 'verticalAlign' => 'center'], [
            Blocks::make('image', 0, 0, 5, 20, [], ['radius' => '2xl'], ['preset' => 'zoom-in']),
            Blocks::make('heading', 6, 2, 6, 4, ['text' => $title, 'level' => 'h2', 'size' => 'lg'], motion: ['delay' => 80]),
            Blocks::make('text', 6, 7, 6, 8, ['html' => $html, 'size' => 'base', 'color' => 'muted'], motion: ['delay' => 160]),
        ]);
    }

    /**
     * Tres columnas con ícono, título y texto.
     *
     * @return array<string, mixed>
     */
    private static function values(): array
    {
        $columns = [
            ['pencil-ruler', 'Diseñamos contigo', 'Medimos tu espacio y proponemos un diseño a la medida de tu forma de vivir.'],
            ['hammer', 'Fabricamos en taller', 'Cortamos, armamos y revisamos cada pieza antes de llevarla a tu casa.'],
            ['house', 'Instalamos y cuidamos', 'Nuestro equipo instala, limpia al terminar y te acompaña después.'],
        ];

        $blocks = [
            Blocks::make('badge', 0, 0, 6, 1, ['text' => 'Cómo trabajamos', 'variant' => 'eyebrow', 'color' => 'oak-ink'], motion: ['preset' => 'fade-in']),
            Blocks::make('heading', 0, 2, 7, 4, ['text' => 'Del boceto a la instalación, con el mismo equipo', 'level' => 'h2', 'size' => 'lg'], motion: ['delay' => 80]),
        ];
        foreach ($columns as $i => [$icon, $heading, $text]) {
            $x = $i * 4;
            $delay = 120 + $i * 80;
            $blocks[] = Blocks::make('icon', $x, 8, 1, 2, ['name' => $icon, 'size' => 'md', 'color' => 'primary', 'framed' => true], motion: ['delay' => $delay]);
            $blocks[] = Blocks::make('heading', $x, 11, 4, 2, ['text' => $heading, 'level' => 'h3', 'size' => 'sm'], motion: ['delay' => $delay]);
            $blocks[] = Blocks::make('text', $x, 13, 4, 4, ['html' => Blocks::paragraphs($text), 'size' => 'base', 'color' => 'muted'], motion: ['delay' => $delay]);
        }

        return Blocks::layout(['name' => 'Cómo trabajamos', 'background' => ['kind' => 'color', 'color' => 'surface']], $blocks);
    }

    /**
     * Portada oscura con video incrustado.
     *
     * @return array<string, mixed>
     */
    private static function videoHero(string $title): array
    {
        return Blocks::layout([
            'name' => 'Portada con video',
            'background' => ['kind' => 'gradient', 'gradient' => 'espresso-depth'],
            'padding' => 'hero',
            'verticalAlign' => 'center',
            'tone' => 'dark',
            'overlayHeader' => true,
        ], [
            Blocks::make('badge', 0, 2, 5, 2, ['text' => 'Muebles a la medida', 'variant' => 'glass'], motion: ['preset' => 'fade-in']),
            Blocks::make('heading', 0, 5, 5, 9, ['text' => Str::limit($title, 200, ''), 'level' => 'h1', 'size' => 'lg'], motion: ['preset' => 'mask-reveal', 'delay' => 80]),
            Blocks::make('text', 0, 15, 5, 4, ['html' => Blocks::paragraphs('Mira cómo diseñamos, fabricamos e instalamos un proyecto de principio a fin.'), 'size' => 'lead'], ['opacity' => 90], ['delay' => 160]),
            Blocks::make('button', 0, 20, 3, 2, ['label' => 'Solicita una asesoría', 'href' => '/solicitar', 'variant' => 'light'], motion: ['delay' => 240]),
            Blocks::make('embed', 6, 2, 6, 19, ['title' => 'Video de ML Habitat'], ['radius' => '2xl', 'shadow' => 'lg'], ['preset' => 'zoom-in', 'delay' => 120]),
        ]);
    }
}
