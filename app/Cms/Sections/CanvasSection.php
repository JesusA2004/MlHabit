<?php

namespace App\Cms\Sections;

/**
 * Lienzo libre del editor visual: no tiene datos propios; todo su
 * contenido vive en los bloques del lienzo (`site_sections.layout`).
 */
final class CanvasSection extends SectionSchema
{
    public function label(): string
    {
        return 'Lienzo libre';
    }

    public function description(): string
    {
        return 'Sección libre: acomoda títulos, textos, imágenes y botones donde quieras.';
    }

    public function fields(): array
    {
        return [];
    }
}
