<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro anónimo de una visita a una pantalla pública.
 *
 * No almacena datos personales: el visitante se identifica con un hash
 * rotativo diario (sin IP ni cookie) usado solo para distinguir visitas
 * únicas. Ver App\Http\Middleware\TrackPageView.
 *
 * @property int $id
 * @property string $path
 * @property string|null $label
 * @property string|null $referrer_host
 * @property string $visitor_hash
 * @property bool $is_unique
 * @property CarbonImmutable $viewed_on
 * @property CarbonImmutable|null $created_at
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['path', 'label', 'referrer_host', 'visitor_hash', 'is_unique', 'viewed_on'];

    protected function casts(): array
    {
        return [
            'is_unique' => 'boolean',
            'viewed_on' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Visitas dentro de los últimos {$days} días (incluido hoy).
     *
     * @param  Builder<PageView>  $query
     */
    public function scopeSince(Builder $query, int $days): void
    {
        $query->where('viewed_on', '>=', now()->subDays(max(0, $days - 1))->toDateString());
    }
}
