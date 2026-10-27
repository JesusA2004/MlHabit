<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class ProcessSection extends SectionSchema
{
    public function label(): string
    {
        return 'Cómo funciona';
    }

    public function description(): string
    {
        return 'Etapas del proceso, de la asesoría al seguimiento, en una línea de tiempo numerada.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Cómo trabajamos', max: 80),
            Field::text('title', 'Título', 'Un proceso claro, de la idea a la instalación', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', 'Sabrás en todo momento en qué etapa está tu proyecto y qué sigue.', max: 300),
            Field::list('steps', 'Etapas', [
                Field::text('title', 'Etapa', required: true, max: 60),
                Field::textarea('body', 'Descripción', max: 280),
            ], self::defaultSteps(), min: 1, max: 10),
        ];
    }

    /** @return list<array{title: string, body: string}> */
    public static function defaultSteps(): array
    {
        return [
            ['title' => 'Asesoría', 'body' => 'Platicamos sobre tu espacio, lo que necesitas y tu presupuesto para orientarte desde el inicio.'],
            ['title' => 'Visita y medición', 'body' => 'Un técnico visita tu domicilio para tomar medidas exactas y revisar las condiciones del espacio.'],
            ['title' => 'Diseño', 'body' => 'Preparamos una propuesta de diseño que puedes revisar y ajustar antes de aprobarla.'],
            ['title' => 'Cotización', 'body' => 'Recibes una cotización detallada, con materiales y acabados definidos.'],
            ['title' => 'Fabricación', 'body' => 'Fabricamos tus muebles con las medidas y los acabados que aprobaste.'],
            ['title' => 'Instalación', 'body' => 'Nuestro equipo instala en tu domicilio y deja el espacio listo para usarse.'],
            ['title' => 'Seguimiento', 'body' => 'Revisamos contigo el resultado y te acompañamos después de la entrega.'],
        ];
    }
}
