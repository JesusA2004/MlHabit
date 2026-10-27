<?php

namespace Database\Factories;

use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => SupplierType::Both,
            'status' => SupplierStatus::Active,
            'email' => fake()->unique()->companyEmail(),
            'phone' => '55'.fake()->numerify('########'),
            'lead_time_days' => 15,
        ];
    }

    public function fabricator(): static
    {
        return $this->state(fn () => ['type' => SupplierType::Fabricator]);
    }

    public function installer(): static
    {
        return $this->state(fn () => ['type' => SupplierType::Installer]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => SupplierStatus::Inactive]);
    }
}
