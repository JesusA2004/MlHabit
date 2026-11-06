<?php

namespace Database\Factories;

use App\Models\SitePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SitePage>
 */
class SitePageFactory extends Factory
{
    protected $model = SitePage::class;

    public function definition(): array
    {
        $title = 'Página '.fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'seo_title' => null,
            'seo_description' => null,
            'template' => 'blank',
            'is_published' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()]);
    }
}
