<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avance de una persona en un recorrido guiado (`completed` o `dismissed`).
 *
 * @property int $id
 * @property int $user_id
 * @property string $tour
 * @property string $status
 * @property int|null $last_step
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserTourState extends Model
{
    public const STATUSES = ['completed', 'dismissed'];

    protected $fillable = ['user_id', 'tour', 'status', 'last_step'];

    protected function casts(): array
    {
        return [
            'last_step' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
