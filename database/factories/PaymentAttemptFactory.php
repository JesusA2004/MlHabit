<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'number' => 1,
            'idempotency_key' => fn () => (string) Str::uuid(),
            'status' => PaymentAttemptStatus::Sending,
            'ip_address' => '201.141.10.20',
        ];
    }
}
