<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Notificación recibida de la pasarela (payload saneado). `dedupe_key` UNIQUE
 * garantiza que un reenvío del mismo evento se procese una sola vez.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_type
 * @property string $dedupe_key
 * @property string|null $transaction_id
 * @property array<string, mixed> $payload
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $received_at
 * @property Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'provider', 'event_type', 'dedupe_key', 'transaction_id', 'payload', 'status',
        'attempts', 'error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
