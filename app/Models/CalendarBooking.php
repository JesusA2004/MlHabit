<?php

namespace App\Models;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ocupación real de la agenda de un usuario (asesor/técnico).
 *
 * @property int $id
 * @property int $user_id
 * @property BookingKind $kind
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $buffer_minutes
 * @property string|null $bookable_type
 * @property int|null $bookable_id
 * @property BookingStatus $status
 * @property Carbon|null $hold_expires_at
 * @property string|null $slot_key
 */
class CalendarBooking extends Model
{
    protected $fillable = [
        'user_id', 'kind', 'starts_at', 'ends_at', 'buffer_minutes',
        'bookable_type', 'bookable_id', 'status', 'hold_expires_at', 'slot_key',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'buffer_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Reservas que ocupan tiempo: confirmadas o apartados vigentes.
     *
     * @param  Builder<CalendarBooking>  $query
     */
    public function scopeOccupying(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->where('status', BookingStatus::Confirmed->value)
                ->orWhere(fn (Builder $h) => $h->where('status', BookingStatus::Held->value)->where('hold_expires_at', '>', now()));
        });
    }

    public function isActive(): bool
    {
        return $this->status === BookingStatus::Confirmed
            || ($this->status === BookingStatus::Held && $this->hold_expires_at?->isFuture());
    }
}
