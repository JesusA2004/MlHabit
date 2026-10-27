<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = 'Página '.fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'summary' => fake()->sentence(),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'version' => 1,
            'is_published' => true,
            'show_in_footer' => true,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false, 'published_at' => null]);
    }
}
