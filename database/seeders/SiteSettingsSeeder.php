<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'hero' => [
                'eyebrow' => 'SIN TAPUJOS.',
                'title_lines' => ['NEGOCIOS.', 'HÁBITOS.', 'VIDA.'],
                'primary_cta_label' => 'Sígueme en Instagram',
                'secondary_cta_label' => 'Explora los tips',
                'tertiary_link_label' => 'También en TikTok',
                'photo_media_id' => null,
            ],
            'empieza_aqui' => [
                'title' => 'CONOCE MI PERSPECTIVA.',
            ],
            'tips_section' => [
                'title' => 'IDEAS QUE DEJAN HUELLA.',
                'posts_per_page' => 9,
            ],
            'historia_preview' => [
                'title' => 'MI HISTORIA',
                'intro' => '',
                'photo_media_id' => null,
            ],
            'proceso_preview' => [
                'title' => '365 DÍAS DE CUIDAR DE MÍ.',
            ],
            'footer' => [
                'tagline' => 'NEGOCIOS. HÁBITOS. VIDA. — SIN TAPUJOS.',
            ],
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
