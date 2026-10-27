<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\ClientConformity;
use App\Enums\InstallationStatus;
use Database\Factories\InstallationOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Orden de instalación (folio I). Precios, movilización y mínimo quedan
 * CONGELADOS desde el tarifario vigente al crearse: cambios posteriores al
 * tarifario nunca la afectan. Totales calculados en backend
 * (InstallationTotals).
 *
 * @property int $id
 * @property string $folio
 * @property int $project_id
 * @property int $supplier_id
 * @property int $rate_card_id
 * @property int|null $lead_installer_id
 * @property InstallationStatus $status
 * @property Carbon|null $scheduled_start
 * @property Carbon|null $scheduled_end
 * @property string $mobilization_fee
 * @property string $minimum_charge
 * @property string $subtotal
 * @property string $total
 * @property ClientConformity|null $client_conformity
 * @property string|null $client_comment
 * @property Carbon|null $client_signed_at
 * @property int|null $client_signed_by
 * @property Carbon|null $started_at
 * @property Carbon|null $evidence_submitted_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $items_count
 * @property-read int|null $done_count
 * @property-read int|null $pending_extras_count
 * @property-read int|null $open_incidents_count
 */
class InstallationOrder extends Model
{
    /** @use HasFactory<InstallationOrderFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'folio', 'project_id', 'supplier_id', 'rate_card_id', 'lead_installer_id', 'status',
        'scheduled_start', 'scheduled_end', 'mobilization_fee', 'minimum_charge', 'subtotal', 'total',
        'client_conformity', 'client_comment', 'client_signed_at', 'client_signed_by',
        'started_at', 'evidence_submitted_at', 'completed_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InstallationStatus::class,
            'client_conformity' => ClientConformity::class,
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'mobilization_fee' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'client_signed_at' => 'datetime',
            'started_at' => 'datetime',
            'evidence_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
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

    /** @return BelongsTo<RateCard, $this> */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }

    /** @return BelongsTo<User, $this> */
    public function leadInstaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_installer_id');
    }

    /** @return HasMany<InstallationOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InstallationOrderItem::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<QualityInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(QualityInspection::class);
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** @return HasOne<SupplierPayout, $this> */
    public function payout(): HasOne
    {
        return $this->hasOne(SupplierPayout::class);
    }

    public function isLedBy(?User $user): bool
    {
        return $user !== null && $this->lead_installer_id !== null && $this->lead_installer_id === $user->id;
    }
}
