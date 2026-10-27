<?php

namespace Database\Factories;

use App\Enums\PrequalificationStatus;
use App\Models\Lead;
use App\Models\Prequalification;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prequalification>
 */
class PrequalificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => Folio::next('Q'),
            'lead_id' => Lead::factory(),
            'customer_id' => fn (array $attributes) => Lead::query()->whereKey($attributes['lead_id'])->value('customer_id'),
            'status' => PrequalificationStatus::Draft,
        ];
    }

    public function status(PrequalificationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
