<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Proyecto del cliente: contenedor estable (folio) de medición, diseño,
 * cotización, contrato, fabricación, instalación, calidad y garantías.
 * El estado solo cambia mediante App\Services\Projects\ProjectWorkflow.
 *
 * @property int $id
 * @property string $uuid
 * @property string $folio
 * @property int $customer_id
 * @property int|null $address_id
 * @property int|null $lead_id
 * @property int|null $product_category_id
 * @property string $name
 * @property ProjectStatus $status
 * @property int|null $advisor_id
 * @property int|null $designer_id
 * @property int|null $operations_owner_id
 * @property Carbon|null $target_install_date
 * @property Carbon|null $contracted_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'folio', 'customer_id', 'address_id', 'lead_id', 'product_category_id', 'name', 'status',
        'advisor_id', 'designer_id', 'operations_owner_id', 'target_install_date',
        'contracted_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'target_install_date' => 'date',
            'contracted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function operationsOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operations_owner_id');
    }

    /** @return HasMany<ProjectEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class);
    }

    /** @return HasMany<SiteVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /** @return HasMany<Measurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    /** @return HasMany<DesignVersion, $this> */
    public function designVersions(): HasMany
    {
        return $this->hasMany(DesignVersion::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return HasOne<Contract, $this> */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** @return HasMany<Warranty, $this> */
    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    /** ¿El usuario es el cliente dueño del proyecto? (protección IDOR del portal) */
    public function belongsToUser(User $user): bool
    {
        return $this->customer()->where('user_id', $user->id)->exists();
    }
}
