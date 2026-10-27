<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => 'Casa',
            'street' => fake()->streetName(),
            'exterior_number' => (string) fake()->buildingNumber(),
            'neighborhood' => 'Centro',
            'postal_code' => (string) fake()->numerify('0####'),
            'municipality' => 'Cuauhtémoc',
            'state' => 'Ciudad de México',
        ];
    }
}
