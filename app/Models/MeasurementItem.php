<?php

namespace App\Models;

use App\Enums\MeasurementKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de medición. `value_mm` en milímetros enteros (sin flotantes).
 *
 * @property int $id
 * @property int $measurement_id
 * @property string $area
 * @property MeasurementKind $kind
 * @property string|null $label
 * @property int|null $value_mm
 * @property string|null $notes
 * @property int $sort
 */
class MeasurementItem extends Model
{
    protected $fillable = ['measurement_id', 'area', 'kind', 'label', 'value_mm', 'notes', 'sort'];

    protected function casts(): array
    {
        return [
            'kind' => MeasurementKind::class,
            'value_mm' => 'integer',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Measurement, $this> */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }
}
