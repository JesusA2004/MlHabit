<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cronología del proyecto. visibility=client aparece en el portal.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string $title
 * @property string|null $body
 * @property string $visibility
 * @property array<string, mixed>|null $metadata
 * @property Carbon $occurred_at
 */
class ProjectEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = ['project_id', 'user_id', 'type', 'from_status', 'to_status', 'title', 'body', 'visibility', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
