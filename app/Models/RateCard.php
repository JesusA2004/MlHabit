<?php

namespace App\Models;

use App\Enums\RateCardStatus;
use Database\Factories\RateCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tarifario versionado de instalación. Solo el borrador es editable; al
 * activarse queda inmutable y la versión vigente anterior (mismo nombre)
 * se retira. Las órdenes congelan sus precios al crearse.
 *
 * @property int $id
 * @property string $name
 * @property int $version
 * @property int|null $supplier_id
 * @property RateCardStatus $status
 * @property Carbon|null $effective_from
 * @property string $mobilization_fee
 * @property string $minimum_charge
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $items_count
 */
class RateCard extends Model
{
    /** @use HasFactory<RateCardFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'version', 'supplier_id', 'status', 'effective_from', 'mobilization_fee',
        'minimum_charge', 'notes', 'created_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RateCardStatus::class,
            'version' => 'integer',
            'effective_from' => 'date',
            'mobilization_fee' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
            'activated_at' => 'datetime',
        ];
    }

    /** @return HasMany<RateCardItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RateCardItem::class)->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === RateCardStatus::Draft;
    }
}
