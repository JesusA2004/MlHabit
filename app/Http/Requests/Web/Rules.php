<?php

namespace App\Http\Requests\Web;

use App\Cms\Field;

/**
 * Reglas compartidas por los formularios del módulo web.
 */
final class Rules
{
    public const SLUG = 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @return list<mixed> */
    public static function slug(string $table, ?int $ignoreId = null, int $max = 140): array
    {
        $unique = 'unique:'.$table.',slug'.($ignoreId !== null ? ','.$ignoreId : '');

        return ['nullable', 'string', 'max:'.$max, self::SLUG, $unique];
    }

    /** @return list<mixed> */
    public static function publicImage(): array
    {
        return ['nullable', 'integer', Field::publicImageRule()];
    }

    /**
     * Lista editorial corta [{title, body}].
     *
     * @return array<string, list<mixed>>
     */
    public static function editorialList(string $key, int $max = 12): array
    {
        return [
            $key => ['nullable', 'array', 'max:'.$max],
            $key.'.*.title' => ['required', 'string', 'max:120'],
            $key.'.*.body' => ['nullable', 'string', 'max:600'],
        ];
    }

    /**
     * Galería [{id, origin, caption}] solo con imágenes públicas.
     *
     * @return array<string, list<mixed>>
     */
    public static function gallery(string $key = 'gallery', int $max = 40): array
    {
        return [
            $key => ['nullable', 'array', 'max:'.$max],
            $key.'.*.id' => ['required', 'integer', 'distinct', Field::publicImageRule()],
            $key.'.*.origin' => ['nullable', 'in:render,real_photo'],
            $key.'.*.caption' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function seo(): array
    {
        return [
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ];
    }
}
