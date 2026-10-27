<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Support\Money;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $payment_id
 * @property string $idempotency_key
 * @property string $amount
 * @property string $reason
 * @property RefundStatus $status
 * @property string|null $provider_refund_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int|null $requested_by
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment $payment
 * @property-read User|null $requester
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'payment_id', 'idempotency_key', 'amount', 'reason', 'status', 'provider_refund_id',
        'error_code', 'error_message', 'requested_by', 'processed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Refund $refund) {
            $refund->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
