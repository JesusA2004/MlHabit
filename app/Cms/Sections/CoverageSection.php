<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class CoverageSection extends SectionSchema
{
    public function label(): string
    {
        return 'Cobertura';
    }

    public function description(): string
    {
        return 'Texto sobre las zonas que atendemos y un verificador de código postal.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Cobertura', max: 80),
            Field::text('title', 'Título', '¿Llegamos a tu domicilio?', required: true, max: 120),
            Field::textarea('body', 'Texto', 'Atendemos visitas e instalaciones en zonas específicas. Escribe tu código postal y te decimos al momento si llegamos.', max: 500),
            Field::text('cta_label', 'Texto del botón', 'Revisa si llegamos a tu código postal', required: true, max: 60),
            Field::textarea('fallback', 'Mensaje si no hay cobertura', 'Por ahora no llegamos a esa zona, pero puedes dejarnos tus datos y te avisamos cuando sea posible.', max: 300),
        ];
    }
}
