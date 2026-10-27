<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\VisualOrigin;
use App\Models\ShowcaseProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShowcaseProject>
 */
class ShowcaseProjectFactory extends Factory
{
    protected $model = ShowcaseProject::class;

    public function definition(): array
    {
        $title = 'Proyecto '.fake()->unique()->word().' '.fake()->word();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(10),
            'story' => '<p>'.fake()->paragraph().'</p>',
            'origin' => VisualOrigin::Render,
            'status' => PublicationStatus::Published,
            'is_featured' => true,
            'sort' => 0,
            'published_at' => now(),
        ];
    }

    public function realPhoto(): static
    {
        return $this->state(fn () => ['origin' => VisualOrigin::RealPhoto]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PublicationStatus::Draft, 'published_at' => null]);
    }
}
