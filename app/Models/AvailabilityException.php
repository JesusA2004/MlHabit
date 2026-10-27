<?php

namespace App\Models;

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bloqueo, vacaciones u horario extra. `kind` nulo aplica a todas las agendas.
 *
 * @property int $id
 * @property int $user_id
 * @property BookingKind|null $kind
 * @property AvailabilityExceptionType $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 */
class AvailabilityException extends Model
{
    protected $fillable = ['user_id', 'kind', 'type', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'type' => AvailabilityExceptionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
