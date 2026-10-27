<?php

namespace App\Cms\Layout;

use App\Cms\Field;
use App\Enums\MediaVisibility;
use App\Enums\SiteSectionType;
use App\Models\Media;
use App\Support\Html;
use Illuminate\Validation\ValidationException;

/**
 * Valida y normaliza el borrador de una página del editor visual: lista de
 * secciones con su lienzo (bloques en la retícula de 12 columnas). Todo es
 * lista blanca: tipos de bloque, propiedades, tokens de color, enlaces
 * seguros, texto saneado, límites numéricos y medios públicos.
 *
 * Los errores usan rutas tipo `sections.2.layout.blocks.5.props.href` para
 * que el editor señale el bloque exacto.
 */
final class LayoutValidator
{
    /** Etiquetas permitidas en el texto enriquecido de un bloque de texto. */
    private const RICH_TAGS = '<p><br><strong><b><em><i><u><a><ul><ol><li>';

    private const ID_REGEX = '/^[A-Za-z0-9_-]{2,40}$/';

    private const KEY_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const ANCHOR_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var list<array{path: string, id: int, kind: string}> */
    private array $mediaRefs = [];

    /**
     * Valida un borrador completo `{sections: [...]}`.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array{sections: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public static function snapshot(array $snapshot): array
    {
        $validator = new self;
        $sections = $snapshot['sections'] ?? null;

        if (! is_array($sections) || ! array_is_list($sections)) {
            throw ValidationException::withMessages(['sections' => ['El borrador no tiene secciones válidas.']]);
        }
        if (count($sections) > Grid::MAX_SECTIONS) {
            throw ValidationException::withMessages(['sections' => ['La página admite hasta '.Grid::MAX_SECTIONS.' secciones.']]);
        }

        $out = [];
        $keys = [];
        $anchors = [];
        foreach ($sections as $i => $section) {
            $normalized = $validator->section($section, "sections.$i");
            if ($normalized === null) {
                continue;
            }
            if (in_array($normalized['key'], $keys, true)) {
                $validator->fail("sections.$i.key", 'Hay dos secciones con el mismo identificador.');
            }
            $keys[] = $normalized['key'];

            $anchor = $normalized['layout']['settings']['anchor'];
            if ($anchor !== '') {
                if (in_array($anchor, $anchors, true)) {
                    $validator->fail("sections.$i.layout.settings.anchor", 'Otra sección ya usa este ancla.');
                }
                $anchors[] = $anchor;
            }
            $out[] = $normalized;
        }

        $validator->checkMedia();
        $validator->throwIfFailed();

        return ['sections' => $out];
    }

    /**
     * Valida el lienzo de una sola sección.
     *
     * @param  array<string, mixed>  $layout
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public static function layout(array $layout, string $path = 'layout'): array
    {
        $validator = new self;
        $out = $validator->normalizeLayout($layout, $path);
        $validator->checkMedia();
        $validator->throwIfFailed();

        return $out;
    }

    /**
     * Títulos principales (H1) visibles en la página.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    public static function h1Count(array $sections): int
    {
        $count = 0;
        foreach ($sections as $section) {
            if (($section['isVisible'] ?? true) !== true) {
                continue;
            }
            foreach ($section['layout']['blocks'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'heading' && ($block['props']['level'] ?? null) === 'h1' && count($block['hideOn'] ?? []) < 2) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Medios usados por un lienzo (fondos, imágenes, videos, bloques dinámicos).
     *
     * @param  array<string, mixed>|null  $layout
     * @return list<int>
     */
    public static function mediaIds(?array $layout): array
    {
        if ($layout === null) {
            return [];
        }

        $ids = [];
        $bg = $layout['settings']['background']['mediaId'] ?? null;
        if (is_int($bg)) {
            $ids[] = $bg;
        }
        foreach ($layout['blocks'] ?? [] as $block) {
            foreach (['mediaId', 'media_id'] as $key) {
                if (is_int($block['props'][$key] ?? null)) {
                    $ids[] = $block['props'][$key];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function section(mixed $section, string $p): ?array
    {
        if (! is_array($section)) {
            $this->fail($p, 'Sección inválida.');

            return null;
        }

        $key = $section['key'] ?? null;
        if (! is_string($key) || preg_match(self::KEY_REGEX, $key) !== 1) {
            $this->fail("$p.key", 'La sección no tiene un identificador válido.');
            $key = '';
        }

        $type = is_string($section['type'] ?? null) ? SiteSectionType::tryFrom($section['type']) : null;
        if ($type === null) {
            $this->fail("$p.type", 'Tipo de sección no permitido.');

            return null;
        }

        $layout = $this->normalizeLayout(is_array($section['layout'] ?? null) ? $section['layout'] : [], "$p.layout");

        // Los datos de los bloques dinámicos de una sección curada se
        // reflejan en su contenido tipado (otras páginas lo leen).
        $content = is_array($section['content'] ?? null) ? $section['content'] : [];
        foreach ($layout['blocks'] as $block) {
            if (DynamicBlocks::sourceType($block['type']) === $type) {
                $content = [...$content, ...$block['props']];
            }
        }

        $schema = $type->schema();
        try {
            $content = $schema->validate([...$schema->defaults(), ...$content], "$p.content.");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->errors[$field] = $messages;
            }
            $content = $schema->normalize($content);
        }

        return [
            'key' => strtolower($key),
            'type' => $type->value,
            'isVisible' => ($section['isVisible'] ?? true) === true,
            'content' => $content,
            'layout' => $layout,
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{version: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    private function normalizeLayout(array $layout, string $p): array
    {
        $settings = $this->settings(is_array($layout['settings'] ?? null) ? $layout['settings'] : [], "$p.settings");

        $blocks = $layout['blocks'] ?? [];
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            $this->fail("$p.blocks", 'Los bloques del lienzo no son válidos.');
            $blocks = [];
        }
        if (count($blocks) > Grid::MAX_BLOCKS) {
            $this->fail("$p.blocks", 'Una sección admite hasta '.Grid::MAX_BLOCKS.' elementos.');
            $blocks = array_slice($blocks, 0, Grid::MAX_BLOCKS);
        }

        $out = [];
        $ids = [];
        foreach ($blocks as $j => $block) {
            $normalized = $this->block($block, "$p.blocks.$j", $ids);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return ['version' => Grid::LAYOUT_VERSION, 'settings' => $settings, 'blocks' => $out];
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>
     */
    private function settings(array $s, string $p): array
    {
        $d = Blocks::defaultSettings();
        $bg = is_array($s['background'] ?? null) ? $s['background'] : [];
        $bgKind = $this->enum($bg, 'kind', ['none', 'color', 'image', 'gradient'], 'none', "$p.background");

        $anchor = is_string($s['anchor'] ?? null) ? trim($s['anchor']) : '';
        if ($anchor !== '' && (mb_strlen($anchor) > 40 || preg_match(self::ANCHOR_REGEX, $anchor) !== 1)) {
            $this->fail("$p.anchor", 'El ancla solo admite minúsculas, números y guiones (máximo 40).');
            $anchor = '';
        }

        return [
            'name' => $this->plain($s, 'name', 60, $p),
            'background' => [
                'kind' => $bgKind,
                'color' => $this->color($bg, 'color', Options::SURFACES, $d['background']['color'], "$p.background"),
                'mediaId' => $this->media($bg, 'mediaId', 'image', "$p.background"),
                'overlay' => $this->int($bg, 'overlay', 0, 90, 0, "$p.background"),
                'overlayStyle' => $this->enum($bg, 'overlayStyle', Options::OVERLAY_STYLES, 'flat', "$p.background"),
                'gradient' => $this->enum($bg, 'gradient', Options::GRADIENTS, 'linen-stone', "$p.background"),
            ],
            'padding' => $this->enum($s, 'padding', Options::PADDINGS, $d['padding'], $p),
            'minHeight' => $this->enum($s, 'minHeight', Options::MIN_HEIGHTS, $d['minHeight'], $p),
            'verticalAlign' => $this->enum($s, 'verticalAlign', Options::VERTICAL_ALIGN, $d['verticalAlign'], $p),
            'fullBleed' => $this->bool($s, 'fullBleed', false),
            'surface' => $this->enum($s, 'surface', Options::SURFACE_STYLES, 'none', $p),
            'anchor' => $anchor,
            'tone' => $this->enum($s, 'tone', Options::TONES, 'auto', $p),
            'overlayHeader' => $this->bool($s, 'overlayHeader', false),
            'journey' => $this->enum($s, 'journey', Options::JOURNEYS, '', $p),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>|null
     */
    private function block(mixed $block, string $p, array &$ids): ?array
    {
        if (! is_array($block)) {
            $this->fail($p, 'Elemento inválido.');

            return null;
        }

        $type = $block['type'] ?? null;
        if (! is_string($type) || ! Blocks::exists($type)) {
            $this->fail("$p.type", 'Tipo de elemento no permitido.');

            return null;
        }

        $id = $block['id'] ?? null;
        if (! is_string($id) || preg_match(self::ID_REGEX, $id) !== 1) {
            $this->fail("$p.id", 'El elemento no tiene un identificador válido.');
            $id = Blocks::newId();
        } elseif (in_array($id, $ids, true)) {
            $this->fail("$p.id", 'Hay dos elementos con el mismo identificador.');
        }
        $ids[] = $id;

        $x = $this->int($block, 'x', 0, Grid::COLUMNS - 1, 0, $p, true);
        $w = $this->int($block, 'w', 1, Grid::COLUMNS, Grid::COLUMNS, $p, true);
        if ($x + $w > Grid::COLUMNS) {
            $this->fail("$p.w", 'El elemento se sale de la retícula de '.Grid::COLUMNS.' columnas.');
            $w = Grid::COLUMNS - $x;
        }

        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
        $style = is_array($block['style'] ?? null) ? $block['style'] : [];
        $motion = is_array($block['motion'] ?? null) ? $block['motion'] : [];

        $hideOn = [];
        foreach (is_array($block['hideOn'] ?? null) ? $block['hideOn'] : [] as $device) {
            if (! is_string($device) || ! in_array($device, Options::DEVICES, true)) {
                $this->fail("$p.hideOn", 'Dispositivo no válido.');

                continue;
            }
            $hideOn[] = $device;
        }

        return [
            'id' => $id,
            'type' => $type,
            'x' => $x,
            'y' => $this->int($block, 'y', 0, Grid::MAX_ROW, 0, $p, true),
            'w' => $w,
            'h' => $this->int($block, 'h', 1, Grid::MAX_HEIGHT, 4, $p, true),
            'z' => $this->int($block, 'z', 0, Grid::MAX_Z, 1, $p),
            'props' => $this->props($type, $props, "$p.props"),
            'style' => [
                'align' => $this->enum($style, 'align', Options::ALIGN, 'start', "$p.style"),
                'radius' => $this->enum($style, 'radius', Options::RADIUS, 'none', "$p.style"),
                'shadow' => $this->enum($style, 'shadow', Options::SHADOWS, 'none', "$p.style"),
                'opacity' => $this->int($style, 'opacity', 0, 100, 100, "$p.style"),
                'rotate' => $this->int($style, 'rotate', -180, 180, 0, "$p.style"),
            ],
            'motion' => [
                'preset' => $this->enum($motion, 'preset', Options::MOTION, 'fade-up', "$p.motion"),
                'delay' => $this->int($motion, 'delay', 0, 2000, 0, "$p.motion"),
            ],
            'hideOn' => array_values(array_unique($hideOn)),
            'locked' => $this->bool($block, 'locked', false),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function props(string $type, array $props, string $p): array
    {
        return match ($type) {
            'heading' => [
                'text' => $this->plain($props, 'text', 200, $p),
                'level' => $this->enum($props, 'level', Options::HEADING_LEVELS, 'h2', $p),
                'size' => $this->enum($props, 'size', Options::HEADING_SIZES, 'lg', $p),
                'color' => $this->color($props, 'color', Options::INKS, 'auto', $p),
            ],
            'text' => [
                'html' => $this->rich($props, 'html', 5000, $p),
                'size' => $this->enum($props, 'size', Options::TEXT_SIZES, 'base', $p),
                'color' => $this->color($props, 'color', Options::INKS, 'auto', $p),
            ],
            'button' => [
                'label' => $this->plain($props, 'label', 60, $p, true),
                'href' => $this->link($props, 'href', $p),
                'variant' => $this->enum($props, 'variant', Options::BUTTON_VARIANTS, 'primary', $p),
                'size' => $this->enum($props, 'size', Options::BUTTON_SIZES, 'lg', $p),
                'icon' => $this->bool($props, 'icon', true),
            ],
            'image' => [
                'mediaId' => $this->media($props, 'mediaId', 'image', $p),
                'alt' => $this->plain($props, 'alt', 200, $p),
                'fit' => $this->enum($props, 'fit', Options::IMAGE_FITS, 'cover', $p),
                'focalX' => $this->int($props, 'focalX', 0, 100, 50, $p),
                'focalY' => $this->int($props, 'focalY', 0, 100, 50, $p),
                'zoom' => $this->float($props, 'zoom', 1.0, 3.0, 1.0, $p),
                'showOrigin' => $this->bool($props, 'showOrigin', true),
            ],
            'icon' => [
                'name' => $this->enum($props, 'name', Options::ICONS, 'sparkles', $p),
                'size' => $this->enum($props, 'size', Options::ICON_SIZES, 'md', $p),
                'color' => $this->color($props, 'color', Options::INKS, 'primary', $p),
                'framed' => $this->bool($props, 'framed', true),
            ],
            'badge' => [
                'text' => $this->plain($props, 'text', 80, $p),
                'variant' => $this->enum($props, 'variant', Options::BADGE_VARIANTS, 'eyebrow', $p),
                'color' => $this->color($props, 'color', Options::INKS, 'auto', $p),
            ],
            'shape' => [
                'kind' => $this->enum($props, 'kind', Options::SHAPES, 'rect', $p),
                'color' => $this->color($props, 'color', Options::SURFACES, 'primary-soft', $p),
            ],
            'spacer' => [],
            'video' => [
                'mediaId' => $this->media($props, 'mediaId', 'video', $p),
                'autoplay' => $this->bool($props, 'autoplay', true),
                'loop' => $this->bool($props, 'loop', true),
                'controls' => $this->bool($props, 'controls', false),
            ],
            'stat' => [
                'value' => $this->plain($props, 'value', 24, $p),
                'label' => $this->plain($props, 'label', 80, $p),
                'color' => $this->color($props, 'color', Options::INKS, 'auto', $p),
            ],
            'list' => [
                'items' => $this->listItems($props, $p),
                'marker' => $this->enum($props, 'marker', Options::LIST_MARKERS, 'check', $p),
                'color' => $this->color($props, 'color', Options::INKS, 'auto', $p),
            ],
            default => $this->dynamicProps($type, $props, $p),
        };
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function dynamicProps(string $type, array $props, string $p): array
    {
        $schema = DynamicBlocks::schema($type);
        $input = [...$schema->defaults(), ...array_intersect_key($props, $schema->defaults())];

        // Los medios se revisan en lote al final (una sola consulta).
        $media = [];
        foreach ($schema->fields() as $field) {
            if ($field->kind === Field::MEDIA) {
                $media[$field->key] = $this->media($input, $field->key, 'image', $p);
                unset($input[$field->key]);
            }
        }

        try {
            $out = $schema->validate($input, "$p.");
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->errors[$field] = $messages;
            }
            $out = $schema->normalize($input);
        }

        return [...$out, ...$media];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return list<array{text: string}>
     */
    private function listItems(array $props, string $p): array
    {
        $items = $props['items'] ?? [];
        if (! is_array($items) || count($items) > 12) {
            $this->fail("$p.items", 'La lista admite hasta 12 puntos.');

            return [];
        }

        $out = [];
        foreach (array_values($items) as $i => $item) {
            $text = $this->plain(is_array($item) ? $item : ['text' => $item], 'text', 160, "$p.items.$i");
            if ($text !== '') {
                $out[] = ['text' => $text];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Lectores con lista blanca
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private function enum(array $data, string $key, array $allowed, string $default, string $p): string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }
        if (is_string($data[$key]) && in_array($data[$key], $allowed, true)) {
            return $data[$key];
        }
        $this->fail("$p.$key", 'Valor no permitido.');

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $tokens
     */
    private function color(array $data, string $key, array $tokens, string $default, string $p): string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (is_string($value) && (in_array($value, $tokens, true) || preg_match(Options::HEX, $value) === 1)) {
            return strtolower($value) === $value ? $value : strtolower($value);
        }
        $this->fail("$p.$key", 'Usa un color de la marca o un hexadecimal #RRGGBB.');

        return $default;
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, int $min, int $max, int $default, string $p, bool $required = false): int
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            if ($required) {
                $this->fail("$p.$key", 'Falta la posición o el tamaño del elemento.');
            }

            return $default;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        if (! is_int($value)) {
            $this->fail("$p.$key", 'Debe ser un número entero.');

            return $default;
        }
        if ($value < $min || $value > $max) {
            $this->fail("$p.$key", "Debe estar entre $min y $max.");

            return max($min, min($max, $value));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function float(array $data, string $key, float $min, float $max, float $default, string $p): float
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (! is_int($value) && ! is_float($value)) {
            $this->fail("$p.$key", 'Debe ser un número.');

            return $default;
        }
        if ($value < $min || $value > $max) {
            $this->fail("$p.$key", "Debe estar entre $min y $max.");
        }

        return round(max($min, min($max, (float) $value)), 2);
    }

    /** @param array<string, mixed> $data */
    private function bool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Texto plano sin etiquetas.
     *
     * @param  array<string, mixed>  $data
     */
    private function plain(array $data, string $key, int $max, string $p, bool $required = false): string
    {
        $value = $data[$key] ?? '';
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->fail("$p.$key", 'Debe ser texto.');

            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
        if ($required && $text === '') {
            $this->fail("$p.$key", 'Este texto es obligatorio.');
        }
        if (mb_strlen($text) > $max) {
            $this->fail("$p.$key", "Máximo $max caracteres.");

            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    /**
     * Texto enriquecido limitado (párrafos, negritas, enlaces y listas).
     *
     * @param  array<string, mixed>  $data
     */
    private function rich(array $data, string $key, int $max, string $p): string
    {
        $value = $data[$key] ?? '';
        if (! is_string($value)) {
            $this->fail("$p.$key", 'Debe ser texto.');

            return '';
        }
        $html = Html::clean(strip_tags($value, self::RICH_TAGS));
        if (mb_strlen($html) > $max) {
            $this->fail("$p.$key", 'El texto es demasiado largo.');
        }

        return $html;
    }

    /** @param array<string, mixed> $data */
    private function link(array $data, string $key, string $p): string
    {
        $value = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        if ($value === '' || mb_strlen($value) > 300 || preg_match(Field::SAFE_LINK_REGEX, $value) !== 1) {
            $this->fail("$p.$key", 'Usa una ruta interna (p. ej. /solicitar) o un enlace https://, mailto: o tel:.');

            return '/solicitar';
        }

        return $value;
    }

    /**
     * Referencia a un medio público; se comprueba en lote.
     *
     * @param  array<string, mixed>  $data
     */
    private function media(array $data, string $key, string $kind, string $p): ?int
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }
        if (! is_int($value) || $value < 1) {
            $this->fail("$p.$key", 'Elige un archivo de la biblioteca multimedia.');

            return null;
        }
        $this->mediaRefs[] = ['path' => "$p.$key", 'id' => $value, 'kind' => $kind];

        return $value;
    }

    private function checkMedia(): void
    {
        if ($this->mediaRefs === []) {
            return;
        }

        $found = Media::query()
            ->whereIn('id', array_unique(array_column($this->mediaRefs, 'id')))
            ->get(['id', 'visibility', 'type'])
            ->keyBy('id');

        foreach ($this->mediaRefs as $ref) {
            $media = $found->get($ref['id']);
            $ok = $media instanceof Media
                && $media->visibility === MediaVisibility::Public
                && $media->type->value === $ref['kind'];

            if (! $ok) {
                $this->fail($ref['path'], $ref['kind'] === 'video'
                    ? 'Elige un video público de la biblioteca multimedia.'
                    : 'Elige una imagen pública de la biblioteca multimedia.');
            }
        }
    }

    private function fail(string $path, string $message): void
    {
        $this->errors[$path] = [$message];
    }

    private function throwIfFailed(): void
    {
        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }
    }
}
