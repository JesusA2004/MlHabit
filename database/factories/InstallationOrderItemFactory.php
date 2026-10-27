<?php

namespace Database\Factories;

use App\Enums\InstallationItemStatus;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationOrderItem>
 */
class InstallationOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'installation_order_id' => InstallationOrder::factory(),
            'code' => strtoupper(fake()->bothify('MOD-###')),
            'description' => 'Módulo de prueba',
            'unit' => 'pieza',
            'quantity' => '1.000',
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'is_extra' => false,
            'status' => InstallationItemStatus::Pending,
        ];
    }
}
