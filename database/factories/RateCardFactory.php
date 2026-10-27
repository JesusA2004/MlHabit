<?php

namespace Database\Factories;

use App\Enums\RateCardStatus;
use App\Models\RateCard;
use App\Models\RateCardItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tarifario de prueba. Los importes son datos de prueba, nunca tarifas reales.
 *
 * @extends Factory<RateCard>
 */
class RateCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Tarifario '.fake()->unique()->word(),
            'version' => 1,
            'status' => RateCardStatus::Draft,
            'mobilization_fee' => '0.00',
            'minimum_charge' => '0.00',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => RateCardStatus::Active, 'activated_at' => now()]);
    }

    /**
     * @param  list<array{code: string, unit_price: string, unit?: string, description?: string}>  $items
     */
    public function withItems(array $items): static
    {
        return $this->afterCreating(function (RateCard $card) use ($items) {
            foreach ($items as $i => $item) {
                RateCardItem::query()->create([
                    'rate_card_id' => $card->id,
                    'code' => $item['code'],
                    'description' => $item['description'] ?? "Módulo {$item['code']}",
                    'unit' => $item['unit'] ?? 'pieza',
                    'unit_price' => $item['unit_price'],
                    'sort' => $i,
                ]);
            }
        });
    }
}
