<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'folio' => Folio::next('P'),
            'customer_id' => Customer::factory(),
            'name' => 'Cocina integral '.fake()->lastName(),
            'status' => ProjectStatus::Design,
        ];
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
