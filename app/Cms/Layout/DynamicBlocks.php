<?php

namespace App\Cms\Layout;

use App\Cms\Field;
use App\Cms\Sections\SectionSchema;
use App\Cms\Sections\SubsetSchema;
use App\Enums\SiteSectionType;

/**
 * Bloques dinámicos del editor visual: insertan los componentes curados de
 * la portada (categorías, proyectos, proceso…) con sus datos. Los datos se
 * validan con los mismos campos del esquema de la sección original, así se
 * conservan reglas como "nunca prometer aprobaciones de crédito".
 */
final class DynamicBlocks
{
    /**
     * tipo de bloque → [sección de origen, claves de datos, ¿lista viva?, etiqueta]
     *
     * @var array<string, array{0: SiteSectionType|null, 1: list<string>, 2: bool, 3: string}>
     */
    private const KINDS = [
        'categories' => [SiteSectionType::Categories, ['featured_only', 'limit'], true, 'Categorías del catálogo'],
        'featured_projects' => [SiteSectionType::FeaturedProjects, ['limit'], true, 'Proyectos destacados'],
        'process_steps' => [SiteSectionType::Process, ['steps'], false, 'Pasos del proceso'],
        'benefits_list' => [SiteSectionType::Benefits, ['items'], false, 'Lista de beneficios'],
        'payment_options' => [SiteSectionType::PaymentOptions, ['items', 'disclaimer'], false, 'Formas de pago'],
        'home_visit' => [SiteSectionType::HomeVisit, ['show_price', 'media_id'], false, 'Visita a domicilio'],
        'coverage_checker' => [SiteSectionType::Coverage, ['cta_label', 'fallback'], false, 'Verificador de cobertura'],
        'testimonials' => [SiteSectionType::Testimonials, ['limit'], true, 'Testimonios'],
        'faq' => [SiteSectionType::Faq, ['topics', 'limit'], true, 'Preguntas frecuentes'],
        'final_cta' => [SiteSectionType::FinalCta, ['eyebrow', 'title', 'body', 'primary_cta_label', 'primary_cta_href', 'secondary_cta_label', 'secondary_cta_href', 'media_id'], false, 'Llamada final'],
        'lead_form_cta' => [null, [], false, 'Invitación a solicitar asesoría'],
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::KINDS);
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::KINDS);
    }

    /** Listas vivas: si no hay elementos publicados, no se muestran. */
    public static function isLiveList(string $type): bool
    {
        return (self::KINDS[$type][2] ?? false) === true;
    }

    public static function label(string $type): string
    {
        return self::KINDS[$type][3] ?? $type;
    }

    /** Sección de origen (para sincronizar los datos al publicar). */
    public static function sourceType(string $type): ?SiteSectionType
    {
        return self::KINDS[$type][0] ?? null;
    }

    public static function schema(string $type): SectionSchema
    {
        [$source, $keys, , $label] = self::KINDS[$type];

        if ($source === null) {
            return self::leadFormCta();
        }

        return $source->schema()->only($keys, $label);
    }

    /**
     * Datos iniciales del bloque a partir del contenido de su sección.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function propsFrom(string $type, array $content): array
    {
        $schema = self::schema($type);

        return $schema->normalize([...$schema->defaults(), ...array_intersect_key($content, $schema->defaults())]);
    }

    /** @return array<string, array<string, mixed>> descriptores para el editor */
    public static function descriptors(): array
    {
        $out = [];
        foreach (self::types() as $type) {
            $schema = self::schema($type);
            $out[$type] = [
                'label' => self::label($type),
                'liveList' => self::isLiveList($type),
                'source' => self::sourceType($type)?->value,
                'fields' => array_map(fn (Field $f) => $f->toArray(), $schema->fields()),
                'defaults' => $schema->defaults(),
            ];
        }

        return $out;
    }

    private static function leadFormCta(): SubsetSchema
    {
        return new SubsetSchema('Invitación a solicitar asesoría', 'Tarjeta compacta que invita a llenar la solicitud.', [
            Field::text('title', 'Título', 'Cuéntanos sobre tu proyecto', required: true, max: 120),
            Field::textarea('body', 'Texto', 'Llena la solicitud en menos de tres minutos y un asesor te contacta para orientarte.', max: 300),
            Field::text('cta_label', 'Texto del botón', 'Solicita una asesoría', required: true, max: 40),
            Field::link('cta_href', 'Enlace del botón', '/solicitar', required: true),
        ]);
    }
}
