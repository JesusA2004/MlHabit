<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class CategoriesSection extends SectionSchema
{
    public function label(): string
    {
        return 'Categorías del catálogo';
    }

    public function description(): string
    {
        return 'Muestra las categorías publicadas del catálogo en una cuadrícula editorial.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Lo que hacemos', max: 80),
            Field::text('title', 'Título', 'Muebles a la medida para cada espacio de tu casa', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', 'Explora nuestras líneas y encuentra ideas para tu proyecto.', max: 300),
            Field::toggle('featured_only', 'Solo categorías destacadas', false),
            Field::number('limit', 'Máximo de categorías', 6, 1, 12),
            Field::text('cta_label', 'Texto del enlace al catálogo', 'Ver todo el catálogo', max: 40),
        ];
    }
}
