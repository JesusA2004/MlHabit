<?php

namespace Database\Seeders;

use App\Models\MediaCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea la jerarquía lógica por defecto de la biblioteca multimedia:
 * mr-insight / {negocios,habitos,vida,general} / {fotos,videos,portadas,recursos}
 * tal como se describe en la especificación (sección 8).
 */
class MediaCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $biblioteca = MediaCollection::query()->firstOrCreate(
            ['path' => 'mr-insight'],
            ['parent_id' => null, 'name' => 'MR. INSIGHT', 'slug' => 'mr-insight']
        );

        $colecciones = ['negocios', 'habitos', 'vida', 'general'];
        $carpetas = ['fotos', 'videos', 'portadas', 'recursos'];

        foreach ($colecciones as $coleccion) {
            $nodo = MediaCollection::query()->firstOrCreate(
                ['path' => "mr-insight/{$coleccion}"],
                ['parent_id' => $biblioteca->id, 'name' => Str::headline($coleccion), 'slug' => $coleccion]
            );

            foreach ($carpetas as $carpeta) {
                MediaCollection::query()->firstOrCreate(
                    ['path' => "mr-insight/{$coleccion}/{$carpeta}"],
                    ['parent_id' => $nodo->id, 'name' => Str::headline($carpeta), 'slug' => $carpeta]
                );
            }
        }
    }
}
