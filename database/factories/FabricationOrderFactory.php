<?php

namespace Database\Factories;

use App\Enums\FabricationStatus;
use App\Enums\ProjectStatus;
use App\Models\FabricationOrder;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FabricationOrder>
 */
class FabricationOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('F'),
            'project_id' => fn () => OperationsFixtures::project(ProjectStatus::Fabrication)->id,
            'design_version_id' => fn (array $attrs) => OperationsFixtures::designVersion(Project::query()->whereKey($attrs['project_id'])->firstOrFail()),
            'supplier_id' => Supplier::factory()->fabricator(),
            'status' => FabricationStatus::Draft,
            'cost' => '1000.00',
        ];
    }
}
