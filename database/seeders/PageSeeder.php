<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'key' => 'mi-historia',
                'title' => 'Mi historia',
                'intro' => '',
                'body' => [],
            ],
            [
                'key' => 'mi-proceso',
                'title' => '365 días de cuidar de mí',
                'intro' => '',
                'body' => [],
            ],
            [
                'key' => 'aviso-privacidad',
                'title' => 'Aviso de privacidad',
                'intro' => 'Contenido pendiente de definir por el propietario del sitio.',
                'body' => [],
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(['key' => $page['key']], $page);
        }
    }
}
