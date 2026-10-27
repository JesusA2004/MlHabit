<?php

namespace App\Models;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use Database\Factories\LeadActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bitácora comercial de un prospecto (notas, llamadas, cambios de etapa).
 * Inmutable: solo created_at.
 *
 * @property int $id
 * @property int $lead_id
 * @property int|null $user_id
 * @property LeadActivityType $type
 * @property LeadStatus|null $from_status
 * @property LeadStatus|null $to_status
 * @property string|null $body
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class LeadActivity extends Model
{
    /** @use HasFactory<LeadActivityFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['lead_id', 'user_id', 'type', 'from_status', 'to_status', 'body', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => LeadActivityType::class,
            'from_status' => LeadStatus::class,
            'to_status' => LeadStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
