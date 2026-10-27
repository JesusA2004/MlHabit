<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\FabricationStatus;
use Database\Factories\FabricationOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Orden de fabricación (folio F) a un proveedor fabricante. Solo se crea con
 * un diseño con aprobación técnica.
 *
 * @property int $id
 * @property string $folio
 * @property int $project_id
 * @property int $design_version_id
 * @property int $supplier_id
 * @property FabricationStatus $status
 * @property string|null $cost
 * @property Carbon|null $due_on
 * @property Carbon|null $issued_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $delivered_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FabricationOrder extends Model
{
    /** @use HasFactory<FabricationOrderFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'folio', 'project_id', 'design_version_id', 'supplier_id', 'status', 'cost', 'due_on',
        'issued_at', 'ready_at', 'delivered_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FabricationStatus::class,
            'cost' => 'decimal:2',
            'due_on' => 'date',
            'issued_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<DesignVersion, $this> */
    public function designVersion(): BelongsTo
    {
        return $this->belongsTo(DesignVersion::class);
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
}
