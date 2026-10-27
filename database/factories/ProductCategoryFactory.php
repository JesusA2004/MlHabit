<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = 'Categoría '.fake()->unique()->word().' '.fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'sort' => fake()->numberBetween(0, 100),
            'is_published' => true,
            'is_featured' => false,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
