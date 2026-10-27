<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Refund> */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->paid(),
            'idempotency_key' => fn () => (string) Str::uuid(),
            'amount' => '100.00',
            'reason' => 'Reembolso de prueba',
            'status' => RefundStatus::Pending,
        ];
    }
}
