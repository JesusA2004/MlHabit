<?php

namespace Database\Factories;

use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadActivity>
 */
class LeadActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'type' => LeadActivityType::Note,
            'body' => fake()->sentence(),
        ];
    }
}
