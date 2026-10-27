<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\ProjectStatus;
use App\Models\Incident;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('X'),
            'project_id' => fn () => OperationsFixtures::project(ProjectStatus::Installing)->id,
            'category' => IncidentCategory::MissingPart,
            'severity' => IncidentSeverity::Medium,
            'origin' => IncidentOrigin::Internal,
            'status' => IncidentStatus::Open,
            'title' => 'Falta una pieza',
            'description' => 'Descripción de prueba.',
            'visible_to_client' => true,
        ];
    }
}
