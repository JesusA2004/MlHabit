<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Instantánea inmutable de un documento de Studio.
 *
 * - `kind = publish`: lo que se publicó en ese momento.
 * - `kind = snapshot`: copia de seguridad automática del borrador antes de
 *   restaurar otra versión (la restauración nunca pierde trabajo).
 *
 * @property int $id
 * @property int $studio_page_id
 * @property int $number
 * @property string $kind
 * @property array<string, mixed> $document
 * @property string|null $summary
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 */
class StudioVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['studio_page_id', 'number', 'kind', 'document', 'summary', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'number' => 'integer',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StudioPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(StudioPage::class, 'studio_page_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
