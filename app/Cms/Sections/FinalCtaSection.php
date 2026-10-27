<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class FinalCtaSection extends SectionSchema
{
    public function label(): string
    {
        return 'Llamada final';
    }

    public function description(): string
    {
        return 'Cierre de la portada con la invitación a solicitar una asesoría.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', '', max: 80),
            Field::text('title', 'Título', 'Empecemos a diseñar tu espacio', required: true, max: 120),
            Field::textarea('body', 'Texto', 'Cuéntanos qué necesitas y un asesor se pondrá en contacto contigo para orientarte.', max: 400),
            Field::text('primary_cta_label', 'Botón principal', 'Solicita una asesoría', required: true, max: 40),
            Field::link('primary_cta_href', 'Enlace del botón principal', '/solicitar', required: true),
            Field::text('secondary_cta_label', 'Botón secundario', 'Ver catálogo', max: 40),
            Field::link('secondary_cta_href', 'Enlace del botón secundario', '/catalogo'),
            Field::media('media_id', 'Imagen de fondo', 'Opcional.'),
        ];
    }
}
