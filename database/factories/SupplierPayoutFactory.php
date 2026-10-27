<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Supplier;
use App\Models\SupplierPayout;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayout>
 */
class SupplierPayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('S'),
            'supplier_id' => Supplier::factory(),
            'amount' => '1000.00',
            'status' => PayoutStatus::PendingAuthorization,
        ];
    }
}
