<?php

namespace Database\Factories;

use App\Models\CoverageZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageZone>
 */
class CoverageZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Zona '.fake()->unique()->city(),
            'state' => 'Ciudad de México',
            'municipality' => fake()->city(),
            'is_active' => true,
            'visits_enabled' => true,
            'travel_buffer_minutes' => 30,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
