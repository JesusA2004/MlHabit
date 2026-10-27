<?php

namespace Database\Factories;

use App\Models\RateCard;
use App\Models\RateCardItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCardItem>
 */
class RateCardItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rate_card_id' => RateCard::factory(),
            'code' => strtoupper(fake()->unique()->bothify('MOD-###')),
            'description' => 'Módulo de prueba',
            'unit' => 'pieza',
            'unit_price' => '100.00',
            'sort' => 0,
        ];
    }
}
