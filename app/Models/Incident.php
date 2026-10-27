<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\IncidentCategory;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\ResponsibleParty;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Incidencia (folio X): error de fabricación, faltante, daño, cambio del
 * cliente… Evidencia privada vía HasAttachments (role `evidence`). El
 * portal (projects) lista las que tienen `visible_to_client`.
 *
 * @property int $id
 * @property string $folio
 * @property int $project_id
 * @property int|null $installation_order_id
 * @property int|null $fabrication_order_id
 * @property IncidentCategory $category
 * @property IncidentSeverity $severity
 * @property IncidentOrigin $origin
 * @property IncidentStatus $status
 * @property string $title
 * @property string $description
 * @property ResponsibleParty|null $responsible_party
 * @property int|null $responsible_supplier_id
 * @property int|null $assigned_to
 * @property string|null $action_plan
 * @property string|null $cost
 * @property Carbon|null $due_on
 * @property string|null $resolution
 * @property Carbon|null $resolved_at
 * @property bool $visible_to_client
 * @property int|null $reported_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'folio', 'project_id', 'installation_order_id', 'fabrication_order_id', 'category', 'severity', 'origin',
        'status', 'title', 'description', 'responsible_party', 'responsible_supplier_id', 'assigned_to',
        'action_plan', 'cost', 'due_on', 'resolution', 'resolved_at', 'visible_to_client', 'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => IncidentCategory::class,
            'severity' => IncidentSeverity::class,
            'origin' => IncidentOrigin::class,
            'status' => IncidentStatus::class,
            'responsible_party' => ResponsibleParty::class,
            'cost' => 'decimal:2',
            'due_on' => 'date',
            'resolved_at' => 'datetime',
            'visible_to_client' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<InstallationOrder, $this> */
    public function installationOrder(): BelongsTo
    {
        return $this->belongsTo(InstallationOrder::class);
    }

    /** @return BelongsTo<FabricationOrder, $this> */
    public function fabricationOrder(): BelongsTo
    {
        return $this->belongsTo(FabricationOrder::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function responsibleSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'responsible_supplier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Abiertas o en atención.
     *
     * @param  Builder<Incident>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', IncidentStatus::openValues());
    }

    /**
     * Visibles en el portal del cliente.
     *
     * @param  Builder<Incident>  $query
     */
    public function scopeVisibleToClient(Builder $query): void
    {
        $query->where('visible_to_client', true);
    }
}
