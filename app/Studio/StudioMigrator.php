<?php

namespace App\Studio;

/**
 * Migraciones del esquema de documentos de Studio.
 *
 * - Nivel documento: `schema` (resources/js/studio/schema.json → schemaVersion).
 * - Nivel bloque: `v` por tipo (schema.json → sections|elements.<tipo>.version).
 *
 * Para cambiar la forma de un tipo: sube su `version` en schema.json y
 * registra aquí una función `tipo => [versionOrigen => fn(node): node]`.
 * La misma migración debe existir en resources/js/studio/migrate.ts para
 * documentos que el editor tenga en memoria.
 *
 * Los bloques de tipo desconocido se conservan intactos: el editor los
 * muestra como "no compatible" (recuperable) y el sitio público los omite.
 */
class StudioMigrator
{
    /**
     * Migraciones por tipo: `tipo => [versionOrigen => fn(nodo): nodo]`.
     * Ejemplo: 'hero' => [1 => fn (array $node) => [...$node, 'v' => 2]].
     *
     * @return array<string, array<int, callable(array<string, mixed>): array<string, mixed>>>
     */
    protected function nodeMigrations(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>
     */
    public function migrate(?array $document): array
    {
        if ($document === null || ! isset($document['sections']) || ! is_array($document['sections'])) {
            return ['schema' => StudioSchema::version(), 'sections' => []];
        }

        $document['sections'] = array_map(function ($section) {
            if (! is_array($section)) {
                return $section;
            }
            $section = $this->node($section);
            foreach (($section['slots'] ?? []) as $slot => $children) {
                if (is_array($children)) {
                    $section['slots'][$slot] = array_map(fn ($child) => is_array($child) ? $this->node($child) : $child, $children);
                }
            }

            return $section;
        }, $document['sections']);

        $document['schema'] = StudioSchema::version();

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        $type = $node['type'] ?? null;
        $def = is_string($type) ? (StudioSchema::section($type) ?? StudioSchema::element($type)) : null;
        if ($def === null) {
            return $node;
        }

        $target = (int) $def['version'];
        $current = (int) ($node['v'] ?? 1);
        $steps = $this->nodeMigrations()[$type] ?? [];

        while ($current < $target && isset($steps[$current])) {
            $node = $steps[$current]($node);
            $current++;
        }
        $node['v'] = $current;

        // Nota: PHP serializa mapas vacíos como `[]`; el editor los
        // normaliza a objetos en resources/js/studio/migrate.ts.
        return $node;
    }
}
