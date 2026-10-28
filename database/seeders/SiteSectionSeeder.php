<?php

namespace Database\Seeders;

use App\Cms\Layout\LegacyLayouts;
use App\Models\SiteSection;
use App\Services\Web\SiteBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CONFIGURACIÓN: crea las 11 secciones de la portada con texto sugerido
 * neutral (sin cifras, ciudades ni testimonios inventados) y su lienzo del
 * editor visual. Idempotente: no sobrescribe secciones ya editadas y solo
 * completa el lienzo de las que aún no lo tienen.
 */
class SiteSectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteBuilder::recommendedOrder() as $index => $type) {
            $content = $type->schema()->defaults();

            $section = SiteSection::query()->firstOrCreate(
                ['page' => 'home', 'type' => $type->value],
                [
                    'key' => (string) Str::uuid(),
                    'sort' => ($index + 1) * 10,
                    'is_visible' => true,
                    'content' => $content,
                    'layout' => LegacyLayouts::for($type, $content),
                    'layout_version' => 1,
                ],
            );

            if ($section->layout === null || $section->key === null) {
                $section->forceFill([
                    'key' => $section->key ?? (string) Str::uuid(),
                    'layout' => $section->layout ?? LegacyLayouts::for($type, $section->content),
                ])->save();
            }
        }
    }
}
