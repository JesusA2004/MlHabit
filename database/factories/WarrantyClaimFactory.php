<?php

namespace Database\Factories;

use App\Enums\WarrantyClaimStatus;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarrantyClaim>
 */
class WarrantyClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'folio' => fn () => Folio::next('R'),
            'warranty_id' => Warranty::factory(),
            'description' => 'Reclamo de prueba.',
            'status' => WarrantyClaimStatus::Open,
        ];
    }
}
