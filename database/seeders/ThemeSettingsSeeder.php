<?php

namespace Database\Seeders;

use App\Models\ThemeSetting;
use Illuminate\Database\Seeder;

class ThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'colors' => [
                'background' => '#0D0E10',
                'foreground' => '#F4F1EA',
                'accent' => '#C7AA7D',
                'muted' => '#ABA9A3',
            ],
            'fonts' => [
                'display' => 'Fraunces',
                'body' => 'Instrument Sans',
            ],
            'buttons' => [
                'style' => 'solid',
                'radius' => 'md',
            ],
            'cards' => [
                'radius' => 'lg',
            ],
            'logo' => [
                'logo_media_id' => null,
                'mark_media_id' => null,
                'favicon_media_id' => null,
            ],
            'site' => [
                'name' => 'MR. INSIGHT',
                'subtitle' => 'POR ZURIEL ÁVILA',
            ],
        ];

        foreach ($defaults as $key => $value) {
            ThemeSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
