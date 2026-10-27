<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\WarrantyStatus;
use App\Enums\WarrantyType;
use App\Models\Warranty;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warranty>
 */
class WarrantyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('W'),
            'project_id' => fn () => OperationsFixtures::project(ProjectStatus::Completed)->id,
            'type' => WarrantyType::Product,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'scope' => 'Alcance capturado en prueba.',
            'status' => WarrantyStatus::Active,
        ];
    }
}
