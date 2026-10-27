<?php

namespace App\Models;

use App\Enums\CheckResult;
use App\Enums\QualityCriterion;
use Database\Factories\QualityCheckItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quality_inspection_id
 * @property QualityCriterion $criterion
 * @property CheckResult $result
 * @property string|null $notes
 */
class QualityCheckItem extends Model
{
    /** @use HasFactory<QualityCheckItemFactory> */
    use HasFactory;

    protected $fillable = ['quality_inspection_id', 'criterion', 'result', 'notes'];

    protected function casts(): array
    {
        return [
            'criterion' => QualityCriterion::class,
            'result' => CheckResult::class,
        ];
    }

    /** @return BelongsTo<QualityInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
    }
}
