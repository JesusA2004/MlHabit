<?php

namespace App\Services\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * Genera slugs únicos (incluye registros en papelera, porque el índice
 * UNIQUE de la base también los incluye).
 */
final class Slugger
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, ?string $preferred, string $source, ?int $ignoreId = null, int $max = 140): string
    {
        $base = Str::slug((string) ($preferred !== null && trim($preferred) !== '' ? $preferred : $source));
        $base = mb_substr($base !== '' ? $base : 'elemento', 0, $max - 4);
        $base = rtrim($base, '-');

        $slug = $base;
        $i = 2;
        while (self::exists($model, $slug, $ignoreId)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function exists(string $model, string $slug, ?int $ignoreId): bool
    {
        $query = $model::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }
}
