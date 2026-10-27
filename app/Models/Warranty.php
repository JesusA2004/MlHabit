<?php

namespace App\Models;

use App\Enums\WarrantyStatus;
use App\Enums\WarrantyType;
use Database\Factories\WarrantyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Garantía (folio W). Producto, instalación y servicio adicional se
 * registran por separado. Sin términos fijos en código: alcance,
 * exclusiones y vigencia los captura el equipo.
 *
 * @property int $id
 * @property string $folio
 * @property int $project_id
 * @property WarrantyType $type
 * @property int|null $supplier_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $scope
 * @property string|null $exclusions
 * @property WarrantyStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $open_claims_count
 */
class Warranty extends Model
{
    /** @use HasFactory<WarrantyFactory> */
    use HasFactory;

    protected $fillable = ['folio', 'project_id', 'type', 'supplier_id', 'starts_on', 'ends_on', 'scope', 'exclusions', 'status'];

    protected function casts(): array
    {
        return [
            'type' => WarrantyType::class,
            'status' => WarrantyStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<WarrantyClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class)->latest();
    }

    /** ¿Vigente hoy? */
    public function isInForce(): bool
    {
        return $this->status === WarrantyStatus::Active && $this->ends_on->endOfDay()->isFuture();
    }
}
