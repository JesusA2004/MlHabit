<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SitePageDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Borrador de una página del editor visual: TODAS sus secciones (orden,
 * visibilidad, contenido y lienzo). Se guarda solo (autoguardado) y no se
 * ve en el sitio hasta "Publicar". `revision` evita pisar cambios de otra
 * pestaña o de otra persona.
 *
 * @property int $id
 * @property string $page
 * @property array{sections: list<array<string, mixed>>} $snapshot
 * @property int $revision
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SitePageDraft extends Model
{
    /** @use HasFactory<SitePageDraftFactory> */
    use HasFactory;

    protected $fillable = ['page', 'snapshot', 'revision', 'updated_by'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'revision' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
