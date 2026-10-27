<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Negocios',
                'slug' => 'negocios',
                'description' => 'Decisiones, estrategia y lecciones de construir algo propio.',
                'color' => '#C7AA7D',
                'icon' => 'briefcase',
                'order' => 1,
            ],
            [
                'name' => 'Hábitos',
                'slug' => 'habitos',
                'description' => 'Disciplina, rutinas y lo que realmente cambia el día a día.',
                'color' => '#ABA9A3',
                'icon' => 'repeat',
                'order' => 2,
            ],
            [
                'name' => 'Vida',
                'slug' => 'vida',
                'description' => 'Reflexiones honestas sobre lo que importa fuera del trabajo.',
                'color' => '#F4F1EA',
                'icon' => 'heart',
                'order' => 3,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
