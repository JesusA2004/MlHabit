<?php

namespace Database\Seeders;

use App\Models\MediaCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CONFIGURACIÓN: colecciones (carpetas lógicas) de la biblioteca de ML
 * Habitat. Las rutas `privado/…` corresponden a archivos de clientes que
 * solo se sirven con autorización. También renombra colecciones heredadas
 * del proyecto anterior (`mr-insight/…`) a `sitio/archivo-anterior/…`.
 * Idempotente.
 */
class MediaCollectionSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const TREE = [
        'sitio' => ['portada', 'identidad', 'general'],
        'catalogo' => ['categorias', 'productos', 'acabados'],
        'inspiracion' => ['proyectos'],
        'privado' => ['prospectos', 'visitas', 'proyectos', 'documentos', 'evidencias', 'general'],
    ];

    public function run(): void
    {
        $this->renameLegacy();

        foreach (self::TREE as $root => $children) {
            $parent = MediaCollection::query()->firstOrCreate(
                ['path' => $root],
                ['parent_id' => null, 'name' => Str::headline($root), 'slug' => $root],
            );

            foreach ($children as $child) {
                MediaCollection::query()->firstOrCreate(
                    ['path' => "{$root}/{$child}"],
                    ['parent_id' => $parent->id, 'name' => Str::headline($child), 'slug' => $child],
                );
            }
        }
    }

    private function renameLegacy(): void
    {
        $legacy = MediaCollection::query()->where('path', 'mr-insight')->orWhere('path', 'like', 'mr-insight/%')->orderBy('path')->get();
        if ($legacy->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($legacy) {
            $sitio = MediaCollection::query()->firstOrCreate(['path' => 'sitio'], ['parent_id' => null, 'name' => 'Sitio', 'slug' => 'sitio']);

            foreach ($legacy as $collection) {
                $newPath = 'sitio/archivo-anterior'.substr($collection->path, strlen('mr-insight'));
                if (MediaCollection::query()->where('path', $newPath)->exists()) {
                    continue;
                }
                $collection->update([
                    'path' => $newPath,
                    'name' => $collection->path === 'mr-insight' ? 'Archivo anterior' : $collection->name,
                    'slug' => $collection->path === 'mr-insight' ? 'archivo-anterior' : $collection->slug,
                    'parent_id' => $collection->path === 'mr-insight' ? $sitio->id : $collection->parent_id,
                ]);
            }
        });
    }
}
