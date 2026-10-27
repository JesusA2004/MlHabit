<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un envío de cobro a la pasarela (un clic = una clave de idempotencia).
 *
 * @property int $id
 * @property int $payment_id
 * @property int $number
 * @property string $idempotency_key
 * @property PaymentAttemptStatus $status
 * @property string|null $provider_transaction_id
 * @property int|null $http_status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment $payment
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'payment_id', 'number', 'idempotency_key', 'status', 'provider_transaction_id',
        'http_status', 'error_code', 'error_message', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'number' => 'integer',
            'http_status' => 'integer',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
