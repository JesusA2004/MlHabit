<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro anónimo de una visita a una pantalla pública.
 *
 * No almacena datos personales: el visitante se identifica con un hash
 * rotativo diario (sin IP ni cookie) usado solo para distinguir visitas
 * únicas. Ver App\Http\Middleware\TrackPageView.
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'path', 'post_id', 'studio_page_key', 'label',
        'referrer_host', 'visitor_hash', 'is_unique', 'viewed_on',
    ];

    protected function casts(): array
    {
        return [
            'is_unique' => 'boolean',
            'viewed_on' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** Visitas dentro de los últimos {$days} días (incluido hoy). */
    public function scopeSince(Builder $query, int $days): void
    {
        $query->where('viewed_on', '>=', now()->subDays(max(0, $days - 1))->toDateString());
    }
}
