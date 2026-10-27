<?php

namespace Database\Factories;

use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'folio' => fn () => 'MLH-G-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'idempotency_key' => fn () => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'concept' => PaymentConcept::Other,
            'description' => 'Pago de prueba',
            'method' => PaymentMethod::Card,
            'provider' => PaymentProvider::Openpay,
            'amount' => '1000.00',
            'currency' => 'MXN',
            'status' => PaymentStatus::Pending,
            'refunded_amount' => '0.00',
        ];
    }

    public function paid(string $transactionId = 'trx_test_paid'): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'provider_transaction_id' => $transactionId,
            'provider_authorization' => '801585',
            'card_brand' => 'visa',
            'card_last4' => '1111',
        ]);
    }
}
