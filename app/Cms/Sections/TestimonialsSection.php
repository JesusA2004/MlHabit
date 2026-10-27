<?php

namespace App\Cms\Sections;

use App\Cms\Field;

/**
 * Testimonios publicados CON consentimiento registrado. Si no hay
 * ninguno, la sección completa se oculta en el sitio.
 */
final class TestimonialsSection extends SectionSchema
{
    public function label(): string
    {
        return 'Testimonios';
    }

    public function description(): string
    {
        return 'Opiniones reales de clientes con consentimiento registrado. Si no hay ninguna publicada, la sección no se muestra.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Testimonios', max: 80),
            Field::text('title', 'Título', 'Lo que dicen nuestros clientes', required: true, max: 120),
            Field::number('limit', 'Máximo de testimonios', 6, 1, 12),
        ];
    }
}
