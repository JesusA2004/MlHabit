<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class FaqSection extends SectionSchema
{
    public function label(): string
    {
        return 'Preguntas frecuentes';
    }

    public function description(): string
    {
        return 'Preguntas publicadas en formato acordeón, filtradas por tema.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Preguntas frecuentes', max: 80),
            Field::text('title', 'Título', 'Resolvemos tus dudas', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', 'Si no encuentras tu respuesta, escríbenos y con gusto te orientamos.', max: 300),
            Field::topics('topics', 'Temas a mostrar', 'Déjalo vacío para mostrar todos los temas.'),
            Field::number('limit', 'Máximo de preguntas', 8, 1, 20),
            Field::text('cta_label', 'Texto del enlace', 'Ver todas las preguntas', max: 40),
        ];
    }
}
