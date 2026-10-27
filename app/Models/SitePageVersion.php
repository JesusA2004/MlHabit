<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SitePageVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión publicada de una página (historial inmutable). Restaurar copia
 * su `snapshot` al borrador; se vuelve pública al publicar de nuevo.
 *
 * @property int $id
 * @property string $page
 * @property int $number
 * @property array{sections: list<array<string, mixed>>} $snapshot
 * @property string|null $note
 * @property int|null $published_by
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SitePageVersion extends Model
{
    /** @use HasFactory<SitePageVersionFactory> */
    use HasFactory;

    protected $fillable = ['page', 'number', 'snapshot', 'note', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
