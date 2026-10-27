<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Project;
use App\Models\Quote;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cotización con totales directos (sin partidas). Para totales reales usa
 * QuoteService, que recalcula con QuoteCalculator.
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'folio' => Folio::next('C'),
            'project_id' => Project::factory(),
            'version' => 1,
            'status' => QuoteStatus::Draft,
            'title' => 'Cocina integral',
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency' => 'MXN',
            'subtotal' => '0.00',
            'discount_total' => '0.00',
            'tax_rate' => '0.1600',
            'tax_total' => '0.00',
            'total' => '0.00',
            'prices_include_tax' => true,
        ];
    }

    public function sent(string $total = '113500.00'): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
