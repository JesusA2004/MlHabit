<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Proveedor externo: fabrica, instala o ambos. Documentos privados vía
 * HasAttachments (role `document`).
 *
 * @property int $id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $rfc
 * @property SupplierType $type
 * @property SupplierStatus $status
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $capabilities
 * @property int|null $lead_time_days
 * @property string|null $rating
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $active_fabrication_count
 * @property-read int|null $active_installation_count
 * @property-read int|null $technicians_count
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'legal_name', 'rfc', 'type', 'status', 'email', 'phone',
        'capabilities', 'lead_time_days', 'rating', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
            'status' => SupplierStatus::class,
            'lead_time_days' => 'integer',
            'rating' => 'decimal:2',
        ];
    }

    /** @return HasMany<SupplierContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    /** @return HasMany<SupplierEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class)->latest();
    }

    /** @return BelongsToMany<CoverageZone, $this> */
    public function coverageZones(): BelongsToMany
    {
        return $this->belongsToMany(CoverageZone::class);
    }

    /** @return HasMany<TechnicianProfile, $this> */
    public function technicians(): HasMany
    {
        return $this->hasMany(TechnicianProfile::class);
    }

    /** @return HasMany<FabricationOrder, $this> */
    public function fabricationOrders(): HasMany
    {
        return $this->hasMany(FabricationOrder::class);
    }

    /** @return HasMany<InstallationOrder, $this> */
    public function installationOrders(): HasMany
    {
        return $this->hasMany(InstallationOrder::class);
    }

    /** @return HasMany<RateCard, $this> */
    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    /** @return HasMany<SupplierPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(SupplierPayout::class);
    }

    public function fabricates(): bool
    {
        return in_array($this->type, [SupplierType::Fabricator, SupplierType::Both], true);
    }

    public function installs(): bool
    {
        return in_array($this->type, [SupplierType::Installer, SupplierType::Both], true);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SupplierStatus::Active->value);
    }

    /**
     * Proveedores activos que pueden fabricar.
     *
     * @param  Builder<Supplier>  $query
     */
    public function scopeFabricators(Builder $query): void
    {
        $query->whereIn('type', [SupplierType::Fabricator->value, SupplierType::Both->value]);
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeInstallers(Builder $query): void
    {
        $query->whereIn('type', [SupplierType::Installer->value, SupplierType::Both->value]);
    }
}
