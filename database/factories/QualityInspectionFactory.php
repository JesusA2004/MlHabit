<?php

namespace Database\Factories;

use App\Enums\QualityResult;
use App\Enums\QualityStage;
use App\Models\InstallationOrder;
use App\Models\QualityInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityInspection>
 */
class QualityInspectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'installation_order_id' => InstallationOrder::factory(),
            'stage' => QualityStage::Quality,
            'result' => QualityResult::Pending,
        ];
    }
}
