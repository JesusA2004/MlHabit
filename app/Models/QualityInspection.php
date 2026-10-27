<?php

namespace App\Models;

use App\Enums\QualityResult;
use App\Enums\QualityStage;
use Database\Factories\QualityInspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Inspección por partida (o por orden): `installer` = autoevaluación con
 * evidencia; `quality` = revisión interna.
 *
 * @property int $id
 * @property int $installation_order_id
 * @property int|null $installation_order_item_id
 * @property QualityStage $stage
 * @property QualityResult $result
 * @property string|null $observations
 * @property bool $client_contacted
 * @property int|null $inspector_id
 * @property Carbon|null $inspected_at
 */
class QualityInspection extends Model
{
    /** @use HasFactory<QualityInspectionFactory> */
    use HasFactory;

    protected $fillable = [
        'installation_order_id', 'installation_order_item_id', 'stage', 'result',
        'observations', 'client_contacted', 'inspector_id', 'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => QualityStage::class,
            'result' => QualityResult::class,
            'client_contacted' => 'boolean',
            'inspected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<InstallationOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(InstallationOrder::class, 'installation_order_id');
    }

    /** @return BelongsTo<InstallationOrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InstallationOrderItem::class, 'installation_order_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /** @return HasMany<QualityCheckItem, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(QualityCheckItem::class);
    }
}
