<?php

namespace App\Studio;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Page;

/**
 * Recorre un documento de Studio y extrae sus referencias externas
 * (multimedia y páginas del CMS) guiándose por los tipos `media`/`page`
 * declarados en schema.json — nunca por heurísticas sobre nombres.
 */
class StudioReferences
{
    /**
     * IDs de multimedia referenciados directamente en el documento.
     *
     * @param  array<string, mixed>  $document
     * @return list<int>
     */
    public function mediaIds(array $document): array
    {
        $ids = [];
        $this->walk($document, function (array $node, array $propDefs) use (&$ids) {
            $this->collect($node['props'] ?? [], $propDefs, 'media', $ids);
        });

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Claves de páginas del CMS referenciadas (binds, page-body, timeline).
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public function pageKeys(array $document): array
    {
        $keys = [];
        $this->walk($document, function (array $node, array $propDefs) use (&$keys) {
            $this->collect($node['props'] ?? [], $propDefs, 'page', $keys);
        });

        return array_values(array_unique(array_filter($keys, fn ($k) => is_string($k) && $k !== '')));
    }

    /**
     * Problemas que impedirían que la página publicada se viera bien.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    public function problems(array $document): array
    {
        $problems = [];
        $ids = $this->mediaIds($document);

        if ($ids !== []) {
            $found = Media::query()->whereIn('id', $ids)->get(['id', 'status', 'original_filename'])->keyBy('id');
            foreach ($ids as $id) {
                $media = $found->get($id);
                if ($media === null) {
                    $problems[] = "Un bloque usa un archivo multimedia que ya no existe (#$id).";
                } elseif ($media->status !== MediaStatus::Ready) {
                    $problems[] = "«{$media->original_filename}» todavía se está procesando o falló.";
                }
            }
        }

        $keys = $this->pageKeys($document);
        if ($keys !== []) {
            $existing = Page::query()->whereIn('key', $keys)->pluck('key')->all();
            foreach (array_diff($keys, $existing) as $missing) {
                $problems[] = "La página del CMS «{$missing}» no existe.";
            }
        }

        return $problems;
    }

    /**
     * Quita las referencias a un medio (borrado forzado confirmado).
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function withoutMedia(array $document, int $mediaId): array
    {
        foreach (($document['sections'] ?? []) as $i => $section) {
            $document['sections'][$i] = $this->stripNode($section, $mediaId);
            foreach (($section['slots'] ?? []) as $slot => $children) {
                foreach ($children as $j => $child) {
                    $document['sections'][$i]['slots'][$slot][$j] = $this->stripNode($child, $mediaId);
                }
            }
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function stripNode(array $node, int $mediaId): array
    {
        $defs = $this->propDefs($node);
        if ($defs !== null) {
            $node['props'] = $this->stripProps($node['props'] ?? [], $defs, $mediaId);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, array<string, mixed>>  $defs
     * @return array<string, mixed>
     */
    private function stripProps(array $props, array $defs, int $mediaId): array
    {
        foreach ($defs as $key => $def) {
            if ($def['kind'] === 'media' && (int) ($props[$key] ?? 0) === $mediaId) {
                $props[$key] = null;
            }
            if ($def['kind'] === 'list' && is_array($props[$key] ?? null)) {
                // Los elementos quedan con media = null; el renderer omite
                // las fotos vacías de galerías y las imágenes sin archivo.
                $props[$key] = array_map(
                    fn ($item) => is_array($item) ? $this->stripProps($item, $def['item'], $mediaId) : $item,
                    $props[$key],
                );
            }
        }

        return $props;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  callable(array<string, mixed>, array<string, array<string, mixed>>): void  $visit
     */
    private function walk(array $document, callable $visit): void
    {
        foreach (($document['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }
            $defs = $this->propDefs($section);
            if ($defs !== null) {
                $visit($section, $defs);
            }
            foreach (($section['slots'] ?? []) as $children) {
                foreach ((array) $children as $child) {
                    if (is_array($child) && ($childDefs = $this->propDefs($child)) !== null) {
                        $visit($child, $childDefs);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, array<string, mixed>>|null
     */
    private function propDefs(array $node): ?array
    {
        $type = $node['type'] ?? null;
        if (! is_string($type)) {
            return null;
        }
        if ($section = StudioSchema::section($type)) {
            return [...StudioSchema::all()['sharedSectionProps'], ...$section['props']];
        }

        return StudioSchema::element($type)['props'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, array<string, mixed>>  $defs
     * @param  array<int, mixed>  $out
     */
    private function collect(array $props, array $defs, string $kind, array &$out): void
    {
        foreach ($defs as $key => $def) {
            $value = $props[$key] ?? null;
            if ($def['kind'] === $kind && $value !== null && $value !== '') {
                $out[] = $value;
            }
            if ($def['kind'] === 'list' && is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $this->collect($item, $def['item'], $kind, $out);
                    }
                }
            }
        }
    }
}
