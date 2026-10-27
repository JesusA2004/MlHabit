<?php

namespace App\Cms\Layout;

use App\Enums\SiteSectionType;

/**
 * Plantillas para "Agregar sección" en el editor visual. Las secciones
 * curadas usan el mismo lienzo que la conversión de la portada; las libres
 * son lienzos con bloques de ejemplo (texto neutral, sin cifras inventadas).
 */
final class Templates
{
    /**
     * @return list<array{id: string, label: string, description: string, icon: string, type: string, content: array<string, mixed>, layout: array<string, mixed>}>
     */
    public static function all(): array
    {
        $curated = fn (string $id, SiteSectionType $type, string $label, string $icon) => [
            'id' => $id,
            'label' => $label,
            'description' => $type->schema()->description(),
            'icon' => $icon,
            'type' => $type->value,
            'content' => $type->schema()->defaults(),
            'layout' => LegacyLayouts::for($type, $type->schema()->defaults()),
        ];
        $canvas = fn (string $id, string $label, string $description, string $icon, array $layout) => [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'type' => SiteSectionType::Canvas->value,
            'content' => [],
            'layout' => $layout,
        ];

        return [
            $curated('hero', SiteSectionType::Hero, 'Portada', 'panel-top'),
            $canvas('text_image', 'Texto + imagen', 'Título, texto y botón junto a una fotografía.', 'layout-panel-left', self::textImage()),
            $canvas('gallery', 'Galería', 'Composición de tres fotografías con título.', 'layout-grid', self::gallery()),
            $curated('categories', SiteSectionType::Categories, 'Categorías', 'layout-dashboard'),
            $curated('featured_projects', SiteSectionType::FeaturedProjects, 'Proyectos', 'images'),
            $curated('process', SiteSectionType::Process, 'Proceso', 'list-ordered'),
            $curated('benefits', SiteSectionType::Benefits, 'Beneficios', 'badge-check'),
            $curated('payment_options', SiteSectionType::PaymentOptions, 'Formas de pago', 'credit-card'),
            $curated('home_visit', SiteSectionType::HomeVisit, 'Visita', 'ruler'),
            $curated('coverage', SiteSectionType::Coverage, 'Cobertura', 'map-pin'),
            $curated('testimonials', SiteSectionType::Testimonials, 'Testimonios', 'quote'),
            $curated('faq', SiteSectionType::Faq, 'Preguntas', 'circle-help'),
            $curated('final_cta', SiteSectionType::FinalCta, 'CTA final', 'megaphone'),
            $canvas('blank', 'Lienzo en blanco', 'Una sección vacía para acomodar lo que quieras.', 'square-dashed', Blocks::layout(['name' => 'Lienzo en blanco', 'minHeight' => 'md'], [])),
        ];
    }

    /** @return array<string, mixed> */
    private static function textImage(): array
    {
        return Blocks::layout(['name' => 'Texto + imagen', 'verticalAlign' => 'center'], [
            Blocks::make('badge', 0, 2, 6, 1, ['text' => 'Hecho a la medida', 'variant' => 'eyebrow', 'color' => 'oak-ink'], motion: ['preset' => 'fade-in']),
            Blocks::make('heading', 0, 4, 6, 5, ['text' => 'Escribe aquí el título de esta sección', 'level' => 'h2', 'size' => 'lg'], motion: ['delay' => 80]),
            Blocks::make('text', 0, 10, 5, 4, ['html' => '<p>Cuenta en dos o tres líneas qué hace especial este espacio o este servicio.</p>', 'size' => 'lead', 'color' => 'muted'], motion: ['delay' => 160]),
            Blocks::make('button', 0, 15, 3, 2, ['label' => 'Solicita una asesoría', 'href' => '/solicitar', 'variant' => 'primary'], motion: ['delay' => 240]),
            Blocks::make('image', 7, 0, 5, 20, [], ['radius' => '2xl'], ['preset' => 'zoom-in']),
        ]);
    }

    /** @return array<string, mixed> */
    private static function gallery(): array
    {
        return Blocks::layout(['name' => 'Galería'], [
            Blocks::make('heading', 0, 0, 7, 3, ['text' => 'Espacios que inspiran', 'level' => 'h2', 'size' => 'md']),
            Blocks::make('image', 0, 4, 7, 20, [], ['radius' => '2xl'], ['preset' => 'fade-in']),
            Blocks::make('image', 7, 4, 5, 9, [], ['radius' => '2xl'], ['preset' => 'fade-in', 'delay' => 100]),
            Blocks::make('image', 7, 14, 5, 10, [], ['radius' => '2xl'], ['preset' => 'fade-in', 'delay' => 200]),
        ]);
    }
}
