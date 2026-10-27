<?php

namespace App\Models;

use App\Enums\BookingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Disponibilidad semanal recurrente. weekday: 0 = domingo … 6 = sábado.
 *
 * @property int $id
 * @property int $user_id
 * @property BookingKind $kind
 * @property int $weekday
 * @property string $starts_at HH:MM:SS
 * @property string $ends_at HH:MM:SS
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property bool $is_active
 */
class AvailabilityRule extends Model
{
    protected $fillable = ['user_id', 'kind', 'weekday', 'starts_at', 'ends_at', 'effective_from', 'effective_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'weekday' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
