<?php

namespace App\Studio;

/**
 * Valida y sanea un documento de Studio contra schema.json.
 *
 * Reglas de seguridad:
 * - Solo tipos registrados; claves desconocidas se descartan.
 * - Ningún valor llega al CSS sin pasar por un rango numérico, un enum o
 *   un patrón de color hexadecimal: no existe "CSS libre" ni clases libres.
 * - Los enlaces solo admiten rutas internas, http(s), mailto y tel: nunca
 *   `javascript:` ni `data:`.
 * - Los textos se guardan como texto plano (se escapan al renderizar).
 *
 * Devuelve el documento saneado y una lista de errores `ruta => mensaje`.
 */
class StudioDocumentValidator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, true> */
    private array $ids = [];

    private int $nodes = 0;

    /**
     * @return array{document: array<string, mixed>, errors: array<string, string>}
     */
    public function validate(mixed $document): array
    {
        $this->errors = [];
        $this->ids = [];
        $this->nodes = 0;

        $limits = StudioSchema::all()['limits'];

        if (! is_array($document) || ! isset($document['sections']) || ! is_array($document['sections']) || ! array_is_list($document['sections'])) {
            $this->errors['document'] = 'El documento no tiene una lista de secciones válida.';

            return ['document' => ['schema' => StudioSchema::version(), 'sections' => []], 'errors' => $this->errors];
        }

        if (count($document['sections']) > $limits['maxSections']) {
            $this->errors['sections'] = "Una página admite como máximo {$limits['maxSections']} secciones.";
        }

        $sections = [];
        foreach (array_slice($document['sections'], 0, $limits['maxSections']) as $i => $section) {
            $clean = $this->section($section, "sections.$i");
            if ($clean !== null) {
                $sections[] = $clean;
            }
        }

        if ($this->nodes > $limits['maxNodes']) {
            $this->errors['document'] = "La página supera el máximo de {$limits['maxNodes']} bloques.";
        }

        return [
            'document' => ['schema' => StudioSchema::version(), 'sections' => $sections],
            'errors' => $this->errors,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function section(mixed $node, string $path): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            $this->errors[$path] = 'Sección mal formada.';

            return null;
        }

        $def = StudioSchema::section($node['type']);
        if ($def === null) {
            $this->errors[$path] = "Tipo de sección no compatible: {$node['type']}.";

            return null;
        }

        $id = $this->id($node['id'] ?? null, $path);
        $propDefs = [...StudioSchema::all()['sharedSectionProps'], ...$def['props']];
        $props = $this->props($node['props'] ?? [], $propDefs, "$path.props", $def);

        $slots = [];
        $max = StudioSchema::all()['limits']['maxElementsPerSlot'];
        foreach (StudioSchema::keys($def['slots']) as $slot) {
            $children = $node['slots'][$slot] ?? [];
            if (! is_array($children) || ! array_is_list($children)) {
                $children = [];
            }
            if (count($children) > $max) {
                $this->errors["$path.slots.$slot"] = "Una columna admite como máximo $max bloques.";
            }
            $accepts = StudioSchema::slotAccepts($node['type'], $slot);
            $slots[$slot] = [];
            foreach (array_slice($children, 0, $max) as $j => $child) {
                $clean = $this->element($child, "$path.slots.$slot.$j", $accepts);
                if ($clean !== null) {
                    $slots[$slot][] = $clean;
                }
            }
        }

        $this->nodes++;

        return [
            'id' => $id,
            'type' => $node['type'],
            'v' => (int) $def['version'],
            'props' => $props,
            'style' => $this->style($node['style'] ?? [], StudioSchema::stylePropsForGroups($def['styleGroups']), "$path.style"),
            'motion' => $this->motion($node['motion'] ?? null, "$path.motion"),
            'hidden' => (bool) ($node['hidden'] ?? false),
            'slots' => $slots,
        ];
    }

    /**
     * @param  list<string>  $accepts
     * @return array<string, mixed>|null
     */
    private function element(mixed $node, string $path, array $accepts): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            $this->errors[$path] = 'Bloque mal formado.';

            return null;
        }

        $def = StudioSchema::element($node['type']);
        if ($def === null) {
            $this->errors[$path] = "Tipo de bloque no compatible: {$node['type']}.";

            return null;
        }

        if (! in_array($node['type'], $accepts, true)) {
            $this->errors[$path] = "El bloque «{$def['label']}» no puede colocarse en esta zona.";

            return null;
        }

        $this->nodes++;

        return [
            'id' => $this->id($node['id'] ?? null, $path),
            'type' => $node['type'],
            'v' => (int) $def['version'],
            'props' => $this->props($node['props'] ?? [], $def['props'], "$path.props"),
            'style' => $this->style($node['style'] ?? [], StudioSchema::stylePropsForGroups($def['styleGroups']), "$path.style"),
            'motion' => $this->motion($node['motion'] ?? null, "$path.motion"),
            'hidden' => (bool) ($node['hidden'] ?? false),
        ];
    }

    private function id(mixed $id, string $path): string
    {
        if (! is_string($id) || ! preg_match('/^[a-z0-9_-]{4,40}$/', $id)) {
            $this->errors["$path.id"] = 'Identificador de bloque inválido.';

            return 'n_'.bin2hex(random_bytes(4));
        }

        if (isset($this->ids[$id])) {
            $this->errors["$path.id"] = 'Identificador de bloque duplicado.';

            return 'n_'.bin2hex(random_bytes(4));
        }

        $this->ids[$id] = true;

        return $id;
    }

    /**
     * @param  array<string, array<string, mixed>>  $defs
     * @param  array<string, mixed>|null  $sectionDef
     * @return array<string, mixed>
     */
    private function props(mixed $values, array $defs, string $path, ?array $sectionDef = null): array
    {
        $values = is_array($values) ? $values : [];
        $out = [];

        foreach ($defs as $key => $def) {
            $value = array_key_exists($key, $values) ? $values[$key] : ($def['default'] ?? null);
            $out[$key] = $this->value($value, $def, "$path.$key", $sectionDef);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>|null  $sectionDef
     */
    private function value(mixed $value, array $def, string $path, ?array $sectionDef = null): mixed
    {
        $default = $def['default'] ?? null;

        switch ($def['kind']) {
            case 'string':
            case 'text':
                if ($value === null) {
                    return '';
                }
                if (! is_string($value) && ! is_numeric($value)) {
                    $this->errors[$path] = 'Debe ser texto.';

                    return (string) ($default ?? '');
                }
                $value = $this->plainText((string) $value, $def['kind'] === 'text');
                $max = (int) ($def['max'] ?? 255);
                if (mb_strlen($value) > $max) {
                    $this->errors[$path] = "Máximo $max caracteres.";
                    $value = mb_substr($value, 0, $max);
                }
                if (isset($def['pattern']) && $value !== '' && ! preg_match('/'.$def['pattern'].'/', $value)) {
                    $this->errors[$path] = 'Formato no válido.';

                    return (string) ($default ?? '');
                }

                return $value;

            case 'enum':
                $key = is_scalar($value) ? (string) $value : '';
                if (! array_key_exists($key, $def['options'])) {
                    $this->errors[$path] = 'Opción no válida.';

                    return $default;
                }

                return $key;

            case 'layout':
                $layouts = $sectionDef['layouts'] ?? [];
                if (! is_string($value) || ! array_key_exists($value, $layouts)) {
                    $this->errors[$path] = 'Composición no válida.';

                    return $default;
                }

                return $value;

            case 'bool':
                return (bool) $value;

            case 'int':
            case 'number':
                if (! is_numeric($value)) {
                    $this->errors[$path] = 'Debe ser un número.';

                    return $default;
                }
                $n = $def['kind'] === 'int' ? (int) $value : (float) $value;
                if ($n < $def['min'] || $n > $def['max']) {
                    $this->errors[$path] = "Debe estar entre {$def['min']} y {$def['max']}.";

                    return max($def['min'], min($def['max'], $n));
                }

                return $n;

            case 'media':
                if ($value === null || $value === '' || $value === 0) {
                    return null;
                }
                if (! is_numeric($value) || (int) $value < 1) {
                    $this->errors[$path] = 'Referencia multimedia inválida.';

                    return null;
                }

                return (int) $value;

            case 'category':
            case 'page':
                if ($value === null) {
                    return '';
                }
                if (! is_string($value) || ! preg_match('/^[a-z0-9-]{0,120}$/', $value)) {
                    $this->errors[$path] = 'Referencia no válida.';

                    return (string) ($default ?? '');
                }

                return $value;

            case 'link':
                return $this->link($value, $def, $path);

            case 'list':
                if (! is_array($value) || ! array_is_list($value)) {
                    return [];
                }
                if (count($value) > $def['max']) {
                    $this->errors[$path] = "Máximo {$def['max']} elementos.";
                }
                $items = [];
                foreach (array_slice($value, 0, $def['max']) as $i => $item) {
                    $items[] = $this->props($item, $def['item'], "$path.$i");
                }

                return $items;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array{type: string, value: string, newTab: bool}|null
     */
    private function link(mixed $value, array $def, string $path): ?array
    {
        if ($value === null || $value === []) {
            return ($def['nullable'] ?? false) ? null : ['type' => 'page', 'value' => '/', 'newTab' => false];
        }

        if (! is_array($value)) {
            $this->errors[$path] = 'Enlace mal formado.';

            return null;
        }

        $type = $value['type'] ?? 'page';
        $target = is_string($value['value'] ?? null) ? trim($value['value']) : '';
        $newTab = (bool) ($value['newTab'] ?? false);

        $valid = match ($type) {
            'page' => preg_match('#^/(?!/)[A-Za-z0-9\-._~/?=&%\#]*$#', $target) === 1,
            'url' => preg_match('#^(https?://|mailto:|tel:)[^\s<>"\']+$#i', $target) === 1,
            'social' => preg_match('/^[a-z0-9_-]{1,40}$/', $target) === 1,
            'anchor' => preg_match('/^#?[a-z0-9-]{1,40}$/', $target) === 1,
            default => false,
        };

        if (! $valid || mb_strlen($target) > 500) {
            $this->errors[$path] = 'Destino de enlace no permitido.';

            return ($def['nullable'] ?? false) ? null : ['type' => 'page', 'value' => '/', 'newTab' => false];
        }

        return ['type' => $type, 'value' => $target, 'newTab' => $newTab];
    }

    /**
     * @param  array<string, array<string, mixed>>  $allowed
     * @return array<string, array<string, mixed>>
     */
    private function style(mixed $style, array $allowed, string $path): array
    {
        $out = [];
        if (! is_array($style)) {
            return $out;
        }

        foreach (StudioSchema::breakpoints() as $bp) {
            if (! isset($style[$bp]) || ! is_array($style[$bp])) {
                continue;
            }
            $values = [];
            foreach ($style[$bp] as $key => $value) {
                if (! isset($allowed[$key]) || $value === null) {
                    continue;
                }
                $clean = $this->styleValue($value, $allowed[$key], "$path.$bp.$key");
                if ($clean !== null) {
                    $values[$key] = $clean;
                }
            }
            if ($values !== []) {
                $out[$bp] = $values;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function styleValue(mixed $value, array $def, string $path): mixed
    {
        switch ($def['kind']) {
            case 'number':
                if (! is_numeric($value)) {
                    $this->errors[$path] = 'Debe ser un número.';

                    return null;
                }

                return max($def['min'], min($def['max'], (float) $value));

            case 'enum':
                $key = is_scalar($value) ? (string) $value : '';
                if (! array_key_exists($key, $def['options'])) {
                    $this->errors[$path] = 'Opción no válida.';

                    return null;
                }

                return $key;

            case 'bool':
                return (bool) $value;

            case 'color':
                if (is_string($value) && (in_array($value, StudioSchema::colorTokens(), true) || preg_match('/^#[0-9a-fA-F]{6}$/', $value))) {
                    return $value;
                }
                $this->errors[$path] = 'Color no válido.';

                return null;

            case 'font':
                if (is_string($value) && in_array($value, StudioSchema::fonts(), true)) {
                    return $value;
                }
                $this->errors[$path] = 'Tipografía no disponible.';

                return null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function motion(mixed $motion, string $path): ?array
    {
        if (! is_array($motion)) {
            return null;
        }

        $schema = StudioSchema::all()['motion'];
        $preset = $motion['preset'] ?? 'none';
        if (! is_string($preset) || ! array_key_exists($preset, $schema['presets'])) {
            $this->errors["$path.preset"] = 'Animación no disponible.';

            return null;
        }
        if ($preset === 'none') {
            return null;
        }

        $out = ['preset' => $preset];
        foreach ($schema['fields'] as $key => $range) {
            $n = is_numeric($motion[$key] ?? null) ? (int) $motion[$key] : $range['default'];
            $out[$key] = max($range['min'], min($range['max'], $n));
        }

        $trigger = $motion['trigger'] ?? 'scroll';
        $out['trigger'] = is_string($trigger) && array_key_exists($trigger, $schema['triggers']) ? $trigger : 'scroll';
        $mobile = $motion['mobile'] ?? 'reduce';
        $out['mobile'] = is_string($mobile) && array_key_exists($mobile, $schema['mobile']) ? $mobile : 'reduce';
        $out['once'] = (bool) ($motion['once'] ?? true);

        return $out;
    }

    private function plainText(string $value, bool $multiline): string
    {
        // Sin caracteres de control (salvo saltos de línea en textos largos).
        $value = preg_replace($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $value) ?? '';

        return $multiline ? str_replace("\r", '', $value) : trim($value);
    }
}
