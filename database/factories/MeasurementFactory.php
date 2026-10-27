<?php

namespace Database\Factories;

use App\Enums\MeasurementStatus;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    protected $model = Measurement::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'technician_id' => User::factory(),
            'version' => 1,
            'status' => MeasurementStatus::Draft,
            'space_type' => 'Cocina',
            'recorded_at' => now(),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => MeasurementStatus::Submitted, 'submitted_at' => now()]);
    }
}
