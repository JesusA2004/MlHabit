<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class BenefitsSection extends SectionSchema
{
    public function label(): string
    {
        return 'Beneficios';
    }

    public function description(): string
    {
        return 'Razones para trabajar con ML Habitat, con una imagen opcional.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Por qué ML Habitat', max: 80),
            Field::text('title', 'Título', 'Todo tu proyecto con un solo equipo', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', '', max: 300),
            Field::list('items', 'Beneficios', [
                Field::text('title', 'Beneficio', required: true, max: 80),
                Field::textarea('body', 'Descripción', max: 280),
            ], [
                ['title' => 'Diseño a la medida', 'body' => 'Cada mueble se diseña con las medidas reales de tu espacio y tu forma de usarlo.'],
                ['title' => 'Fabricación e instalación', 'body' => 'Nos encargamos de fabricar e instalar, sin que tengas que coordinar a varios proveedores.'],
                ['title' => 'Seguimiento claro', 'body' => 'Consulta el avance de tu proyecto, tus citas y tus pagos en un solo lugar.'],
                ['title' => 'Acabados para elegir', 'body' => 'Te mostramos materiales y acabados para que decidas con información.'],
            ], min: 1, max: 8),
            Field::media('media_id', 'Imagen', 'Opcional. Fotografía vertical u horizontal de un detalle o un espacio.'),
        ];
    }
}
