<?php

namespace App\Cms\Sections;

use App\Cms\Field;

final class HeroSection extends SectionSchema
{
    public function label(): string
    {
        return 'Portada principal';
    }

    public function description(): string
    {
        return 'Lo primero que ve el visitante: mensaje principal, imagen protagonista y llamadas a la acción.';
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Antetítulo', 'Cocinas, clósets y muebles a la medida', max: 80),
            Field::text('title', 'Título', 'Muebles pensados para tu espacio y tu forma de vivir', required: true, max: 120),
            Field::textarea('subtitle', 'Texto de apoyo', 'Diseñamos, fabricamos e instalamos cocinas integrales, clósets, recámaras y muebles a la medida. Te acompañamos desde la primera idea hasta la instalación.', max: 320),
            Field::text('primary_cta_label', 'Botón principal', 'Solicita una asesoría', required: true, max: 40),
            Field::link('primary_cta_href', 'Enlace del botón principal', '/solicitar', required: true),
            Field::text('secondary_cta_label', 'Botón secundario', 'Ver catálogo', max: 40),
            Field::link('secondary_cta_href', 'Enlace del botón secundario', '/catalogo'),
            Field::media('media_id', 'Imagen protagonista', 'Fotografía o render horizontal de al menos 2400 px. Si no eliges una, se muestra una portada tipográfica.'),
            Field::text('note', 'Nota breve bajo los botones', '', max: 120, help: 'Opcional. Déjalo vacío si no aplica.'),
        ];
    }
}
