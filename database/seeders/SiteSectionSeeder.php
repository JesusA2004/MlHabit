<?php

namespace Database\Seeders;

use App\Cms\Layout\HomeBlueprint;
use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\LegacyLayouts;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * CONFIGURACIÓN: crea la portada (diseño v3, `HomeBlueprint`) con texto
 * sugerido neutral (sin cifras de negocio, ciudades, precios ni testimonios
 * inventados) y su lienzo del editor visual. Idempotente: si la portada ya
 * existe no la sobrescribe; solo completa el lienzo de secciones antiguas
 * que aún no lo tienen.
 */
class SiteSectionSeeder extends Seeder
{
    public function run(): void
    {
        $existing = SiteSection::query()->where('page', 'home')->get();

        if ($existing->isEmpty() && ! SitePageVersion::query()->where('page', 'home')->exists()) {
            $snapshot = LayoutValidator::snapshot(['sections' => HomeBlueprint::sections()]);
            foreach ($snapshot['sections'] as $index => $section) {
                SiteSection::query()->create([
                    'key' => $section['key'],
                    'page' => 'home',
                    'type' => $section['type'],
                    'sort' => ($index + 1) * 10,
                    'is_visible' => true,
                    'content' => $section['content'],
                    'layout' => $section['layout'],
                    'layout_version' => $section['layout']['version'],
                ]);
            }

            return;
        }

        foreach ($existing as $section) {
            if ($section->layout === null || $section->key === null) {
                $section->forceFill([
                    'key' => $section->key ?? (string) Str::uuid(),
                    'layout' => $section->layout ?? LegacyLayouts::for($section->type, $section->content),
                ])->save();
            }
        }
    }
}
