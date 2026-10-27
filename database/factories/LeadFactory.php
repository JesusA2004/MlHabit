<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => Folio::next('L'),
            'customer_id' => Customer::factory(),
            'status' => LeadStatus::New,
            'postal_code' => (string) fake()->numerify('0####'),
            'city' => fake()->city(),
            'state' => 'Ciudad de México',
            'source' => LeadSource::WebForm,
            'form' => 'solicitar',
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
