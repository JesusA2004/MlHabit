<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Quote;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'folio' => Folio::next('K'),
            'quote_id' => Quote::factory(),
            'project_id' => fn (array $attributes) => Quote::query()->whereKey($attributes['quote_id'])->value('project_id'),
            'status' => ContractStatus::PendingDeposit,
            'contract_amount' => '113500.00',
            'deposit_percent' => '10.00',
            'deposit_amount' => '11350.00',
            'visit_credit_amount' => '0.00',
            'deposit_due_amount' => '11350.00',
            'currency' => 'MXN',
            'signed_at' => now(),
        ];
    }
}
