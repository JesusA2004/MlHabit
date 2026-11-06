<?php

namespace App\Cms\Layout;

/**
 * Listas blancas de valores que acepta el editor visual. Cualquier valor
 * fuera de estas listas se rechaza en el servidor.
 */
final class Options
{
    /** Fondos de sección y rellenos de forma (tokens de la marca). */
    public const SURFACES = ['background', 'surface', 'stone', 'stone-soft', 'linen', 'espresso', 'primary', 'primary-soft', 'oak', 'white'];

    /** Fondos sobre los que el texto automático se vuelve claro. */
    public const DARK_SURFACES = ['espresso', 'primary', 'oak'];

    /** Colores de texto e iconos (tokens de la marca). */
    public const INKS = ['auto', 'foreground', 'muted', 'primary', 'oak', 'oak-ink', 'white', 'espresso'];

    public const HEX = '/^#[0-9a-f]{6}$/i';

    /** `forest-night`: verde profundo del logotipo con luz cálida (portada). */
    public const GRADIENTS = ['linen-stone', 'espresso-depth', 'eucalyptus', 'oak-glow', 'dawn', 'forest-night'];

    /** Gradientes oscuros (texto claro). */
    public const DARK_GRADIENTS = ['espresso-depth', 'eucalyptus', 'forest-night'];

    public const OVERLAY_STYLES = ['flat', 'left', 'bottom'];

    public const PADDINGS = ['none', 'sm', 'md', 'lg', 'xl', 'hero'];

    public const MIN_HEIGHTS = ['auto', 'sm', 'md', 'lg', 'screen'];

    public const VERTICAL_ALIGN = ['start', 'center', 'end'];

    public const SURFACE_STYLES = ['none', 'panel'];

    public const TONES = ['auto', 'light', 'dark'];

    public const ALIGN = ['start', 'center', 'end'];

    public const RADIUS = ['none', 'sm', 'md', 'lg', 'xl', '2xl', 'full'];

    public const SHADOWS = ['none', 'sm', 'md', 'lg'];

    public const MOTION = [
        'none', 'fade-up', 'fade-in', 'zoom-in', 'slide-left', 'slide-right', 'blur-in', 'mask-reveal', 'settle',
        // v2 (motion.css): palabra por palabra, recortes, elevación, rebote…
        'split', 'wipe-up', 'wipe-left', 'wipe-right', 'rise', 'pop', 'blur-up', 'tilt-in', 'zoom-out',
    ];

    /** Pasos del recorrido guiado del sitio (lib/motion/journey.ts). */
    public const JOURNEYS = ['', 'inspirate', 'espacio', 'proceso', 'visita', 'pagos', 'agenda'];

    public const DEVICES = ['tablet', 'mobile'];

    public const HEADING_LEVELS = ['h1', 'h2', 'h3'];

    /** `display`: titular de portada a sangre (más grande que `xl`). */
    public const HEADING_SIZES = ['display', 'xl', 'lg', 'md', 'sm'];

    public const TEXT_SIZES = ['lead', 'base', 'sm'];

    public const BUTTON_VARIANTS = ['primary', 'secondary', 'outline', 'light', 'outline-light', 'oak', 'link'];

    public const BUTTON_SIZES = ['md', 'lg'];

    public const BADGE_VARIANTS = ['eyebrow', 'pill', 'glass', 'soft'];

    /** Galería: cuadrícula, carrusel deslizable o mosaico editorial. */
    public const GALLERY_LAYOUTS = ['grid', 'carousel', 'mosaic'];

    public const GALLERY_GAPS = ['sm', 'md', 'lg'];

    public const GALLERY_ASPECTS = ['4:3', '1:1', '3:4', '16:9'];

    /** Proporción del reproductor de un video incrustado (YouTube/Vimeo). */
    public const EMBED_ASPECTS = ['16:9', '4:3', '1:1', '9:16'];

    /**
     * Formas decorativas. `interior`: escena ilustrada de un espacio (portada
     * sin foto); `materials`: marquesina de materiales; `slats`: panel de
     * listones de madera, como el muro del logotipo.
     */
    public const SHAPES = ['rect', 'circle', 'ring', 'line', 'rings', 'swatches', 'elevation', 'interior', 'materials', 'slats'];

    public const LIST_MARKERS = ['check', 'dot', 'number'];

    public const IMAGE_FITS = ['cover', 'contain'];

    public const ICON_SIZES = ['sm', 'md', 'lg', 'xl'];

    /** Íconos curados (nombres de Lucide en kebab-case). */
    public const ICONS = [
        'ruler', 'pencil-ruler', 'hammer', 'drill', 'paint-roller', 'sofa', 'lamp', 'bed-double',
        'door-open', 'refrigerator', 'cooking-pot', 'utensils', 'shirt', 'house', 'key-round',
        'sparkles', 'shield-check', 'badge-check', 'clock', 'calendar-check', 'map-pin', 'phone',
        'message-circle', 'mail', 'truck', 'wrench', 'leaf', 'layers', 'palette', 'heart', 'star',
        'check', 'arrow-right', 'camera', 'hand-coins', 'credit-card', 'ruler-dimension-line',
    ];

    public static function isSurface(mixed $value): bool
    {
        return is_string($value) && (in_array($value, self::SURFACES, true) || preg_match(self::HEX, $value) === 1);
    }

    public static function isInk(mixed $value): bool
    {
        return is_string($value) && (in_array($value, self::INKS, true) || preg_match(self::HEX, $value) === 1);
    }

    /** ¿Un color de fondo (token o hex) es oscuro? */
    public static function isDark(string $surface): bool
    {
        if (in_array($surface, self::DARK_SURFACES, true)) {
            return true;
        }

        if (preg_match(self::HEX, $surface) !== 1) {
            return false;
        }

        [$r, $g, $b] = sscanf($surface, '#%02x%02x%02x') ?? [255, 255, 255];
        $luminance = (0.299 * (int) $r + 0.587 * (int) $g + 0.114 * (int) $b) / 255;

        return $luminance < 0.5;
    }
}
