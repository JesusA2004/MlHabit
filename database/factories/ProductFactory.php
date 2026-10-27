<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = 'Producto '.fake()->unique()->word().' '.fake()->word();

        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'summary' => fake()->sentence(12),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'benefits' => [['title' => 'Beneficio de prueba', 'body' => fake()->sentence()]],
            'features' => [['title' => 'Característica de prueba', 'body' => null]],
            'show_price' => false,
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'is_featured' => false,
            'sort' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PublicationStatus::Draft, 'published_at' => null]);
    }

    public function withPrice(string $from, ?string $to = null): static
    {
        return $this->state(fn () => ['price_from' => $from, 'price_to' => $to, 'show_price' => true]);
    }
}
