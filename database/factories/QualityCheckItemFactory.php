<?php

namespace Database\Factories;

use App\Enums\CheckResult;
use App\Enums\QualityCriterion;
use App\Models\QualityCheckItem;
use App\Models\QualityInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QualityCheckItem>
 */
class QualityCheckItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quality_inspection_id' => QualityInspection::factory(),
            'criterion' => QualityCriterion::Finish,
            'result' => CheckResult::Pass,
        ];
    }
}
