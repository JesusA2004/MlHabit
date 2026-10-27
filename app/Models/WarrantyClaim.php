<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\WarrantyClaimStatus;
use Database\Factories\WarrantyClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reclamo de garantía (folio R). Mientras haya uno abierto el proyecto
 * está en `warranty_service`. Evidencia privada (role `evidence`).
 *
 * @property int $id
 * @property string $folio
 * @property int $warranty_id
 * @property int|null $incident_id
 * @property string $description
 * @property WarrantyClaimStatus $status
 * @property string|null $resolution
 * @property int|null $reported_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 */
class WarrantyClaim extends Model
{
    /** @use HasFactory<WarrantyClaimFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = ['folio', 'warranty_id', 'incident_id', 'description', 'status', 'resolution', 'reported_by', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'status' => WarrantyClaimStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Warranty, $this> */
    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
