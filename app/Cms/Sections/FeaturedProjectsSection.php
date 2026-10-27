<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class FeaturedProjectsSection extends SectionSchema
{
    public function label(): string
    {
        return 'Proyectos destacados';
    }

    public function description(): string
    {
        return 'Proyectos de inspiración publicados y marcados como destacados. Cada uno indica si es render o proyecto real.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Inspiración', max: 80),
            Field::text('title', 'Título', 'Proyectos y propuestas', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', 'Cada imagen indica si se trata de un proyecto entregado o de un render conceptual.', max: 300),
            Field::number('limit', 'Máximo de proyectos', 3, 1, 9),
            Field::text('cta_label', 'Texto del enlace', 'Ver toda la inspiración', max: 40),
        ];
    }
}
