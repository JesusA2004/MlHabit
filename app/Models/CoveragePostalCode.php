<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coverage_zone_id
 * @property string $postal_code
 */
class CoveragePostalCode extends Model
{
    protected $fillable = ['coverage_zone_id', 'postal_code'];

    /** @return BelongsTo<CoverageZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(CoverageZone::class, 'coverage_zone_id');
    }
}
