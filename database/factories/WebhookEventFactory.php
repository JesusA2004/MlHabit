<?php

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WebhookEvent> */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        $tx = 'trx'.Str::lower(Str::random(17));

        return [
            'provider' => 'openpay',
            'event_type' => 'charge.succeeded',
            'dedupe_key' => 'charge.succeeded:'.$tx.':'.now()->toIso8601String(),
            'transaction_id' => $tx,
            'payload' => ['type' => 'charge.succeeded', 'transaction' => ['id' => $tx]],
            'status' => WebhookEventStatus::Received,
            'received_at' => now(),
        ];
    }
}
