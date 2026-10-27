<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\InstallationItemStatus;
use Database\Factories\InstallationOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Partida de instalación con precio congelado. Los extras (`is_extra`)
 * solo cuentan en el total una vez aprobados por alguien con
 * installations.manage que NO sea el instalador líder. Evidencia (fotos y
 * video) vía HasAttachments role `evidence`.
 *
 * @property int $id
 * @property int $installation_order_id
 * @property int|null $rate_card_item_id
 * @property string $code
 * @property string $description
 * @property string $unit
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 * @property bool $is_extra
 * @property int|null $extra_approved_by
 * @property Carbon|null $extra_approved_at
 * @property InstallationItemStatus $status
 * @property int $sort
 */
class InstallationOrderItem extends Model
{
    /** @use HasFactory<InstallationOrderItemFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'installation_order_id', 'rate_card_item_id', 'code', 'description', 'unit', 'quantity',
        'unit_price', 'line_total', 'is_extra', 'extra_approved_by', 'extra_approved_at', 'status', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_extra' => 'boolean',
            'extra_approved_at' => 'datetime',
            'status' => InstallationItemStatus::class,
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<InstallationOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(InstallationOrder::class, 'installation_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function extraApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extra_approved_by');
    }

    /** @return HasMany<QualityInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class);
    }

    /** ¿Cuenta para el total? Partidas normales siempre; extras solo aprobados. */
    public function countsTowardsTotal(): bool
    {
        if ($this->status === InstallationItemStatus::Rejected) {
            return false;
        }

        return ! $this->is_extra || $this->extra_approved_at !== null;
    }

    public function isPendingExtra(): bool
    {
        return $this->is_extra && $this->extra_approved_at === null && $this->status !== InstallationItemStatus::Rejected;
    }
}
