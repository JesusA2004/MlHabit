<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'author_name' => fake()->firstName(),
            'author_context' => null,
            'quote' => fake()->paragraph(),
            'consent_obtained_at' => null,
            'is_published' => false,
            'sort' => 0,
        ];
    }

    public function withConsent(): static
    {
        return $this->state(fn () => ['consent_obtained_at' => now()->subDay(), 'consent_note' => 'Autorización por escrito']);
    }

    public function published(): static
    {
        return $this->withConsent()->state(fn () => ['is_published' => true]);
    }
}
