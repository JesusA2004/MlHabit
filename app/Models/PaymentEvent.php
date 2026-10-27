<?php

namespace App\Models;

use Database\Factories\PaymentEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bitácora inmutable de un pago (cambios de estado y respuestas seguras).
 * `source`: api | webhook | reconcile | manual | system.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $source
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property array<string, mixed>|null $data
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class PaymentEvent extends Model
{
    /** @use HasFactory<PaymentEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'source', 'type', 'from_status', 'to_status', 'data', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
