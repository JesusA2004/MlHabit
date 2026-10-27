<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEvaluation>
 */
class SupplierEvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'quality_score' => fake()->numberBetween(1, 5),
            'timeliness_score' => fake()->numberBetween(1, 5),
            'communication_score' => fake()->numberBetween(1, 5),
        ];
    }
}
