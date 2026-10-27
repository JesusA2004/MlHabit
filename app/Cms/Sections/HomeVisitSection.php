<?php

namespace App\Cms\Sections;

use App\Cms\Field;

/**
 * Visita técnica a domicilio. El costo NO se escribe aquí: se toma de la
 * política de visita activa (módulo de visitas) y se oculta si no hay una.
 */
final class HomeVisitSection extends SectionSchema
{
    public function label(): string
    {
        return 'Visita técnica';
    }

    public function description(): string
    {
        return 'Explica la visita a domicilio. El costo y si es acreditable se toman de la política de visita activa.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Visita técnica', max: 80),
            Field::text('title', 'Título', 'Medimos tu espacio en tu domicilio', required: true, max: 120),
            Field::textarea('body', 'Texto', 'Un técnico revisa el lugar, toma medidas exactas y resuelve dudas de instalación. Con esa información preparamos un diseño y una cotización a la medida.', max: 500),
            Field::list('points', 'Qué incluye', [
                Field::text('text', 'Punto', required: true, max: 120),
            ], [
                ['text' => 'Medición del espacio'],
                ['text' => 'Revisión de muros, pisos e instalaciones'],
                ['text' => 'Resolución de dudas sobre materiales y acabados'],
            ], max: 6),
            Field::toggle('show_price', 'Mostrar el costo de la visita', true, 'Solo se muestra si existe una política de visita activa.'),
            Field::text('cta_label', 'Texto del botón', 'Agenda tu visita', max: 40),
            Field::link('cta_href', 'Enlace del botón', '/agendar-visita'),
            Field::media('media_id', 'Imagen', 'Opcional.'),
        ];
    }
}
