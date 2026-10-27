<?php

namespace Database\Factories;

use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Models\InstallationOrder;
use App\Models\RateCard;
use App\Models\Supplier;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationOrder>
 */
class InstallationOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('I'),
            'project_id' => fn () => OperationsFixtures::project(ProjectStatus::ReadyToInstall)->id,
            'supplier_id' => Supplier::factory()->installer(),
            'rate_card_id' => RateCard::factory()->active(),
            'status' => InstallationStatus::Draft,
            'mobilization_fee' => '0.00',
            'minimum_charge' => '0.00',
            'subtotal' => '0.00',
            'total' => '0.00',
        ];
    }

    public function status(InstallationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
