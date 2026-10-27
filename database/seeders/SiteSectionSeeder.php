<?php

namespace Database\Seeders;

use App\Enums\SiteSectionType;
use App\Models\SiteSection;
use Illuminate\Database\Seeder;

/**
 * CONFIGURACIÓN: crea las 11 secciones de la portada con texto sugerido
 * neutral (sin cifras, ciudades ni testimonios inventados). Idempotente:
 * no sobrescribe secciones ya editadas.
 */
class SiteSectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteSectionType::cases() as $index => $type) {
            SiteSection::query()->firstOrCreate(
                ['page' => 'home', 'type' => $type->value],
                [
                    'sort' => ($index + 1) * 10,
                    'is_visible' => true,
                    'content' => $type->schema()->defaults(),
                ],
            );
        }
    }
}
