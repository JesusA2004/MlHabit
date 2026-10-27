<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['section_key' => 'hero', 'order' => 1],
            ['section_key' => 'empieza_aqui', 'order' => 2],
            ['section_key' => 'tips', 'order' => 3],
            ['section_key' => 'mi_historia', 'order' => 4],
            ['section_key' => 'mi_proceso', 'order' => 5],
            ['section_key' => 'footer', 'order' => 6],
        ];

        foreach ($sections as $section) {
            HomepageSection::query()->updateOrCreate(
                ['section_key' => $section['section_key']],
                ['order' => $section['order'], 'is_visible' => true]
            );
        }
    }
}
