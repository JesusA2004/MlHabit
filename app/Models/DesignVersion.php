<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\DesignStatus;
use Database\Factories\DesignVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Versión numerada del diseño de un proyecto. Renders y archivos como
 * adjuntos privados (roles `render` y `file`). Una versión enviada al
 * cliente ya no se edita: editar crea la siguiente versión.
 *
 * Aprobación del cliente (layout + acabados) ≠ aprobación técnica
 * (`technical_approved_at`, requisito de ops para fabricar).
 *
 * @property int $id
 * @property int $project_id
 * @property int $number
 * @property string $title
 * @property string|null $notes
 * @property DesignStatus $status
 * @property int|null $measurement_id
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 * @property bool $client_approved_layout
 * @property bool $client_approved_finishes
 * @property Carbon|null $client_decided_at
 * @property int|null $client_decided_by
 * @property string|null $client_comment
 * @property Carbon|null $technical_approved_at
 * @property int|null $technical_approved_by
 * @property string|null $technical_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DesignVersion extends Model
{
    /** @use HasFactory<DesignVersionFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'project_id', 'number', 'title', 'notes', 'status', 'measurement_id', 'created_by', 'sent_at',
        'client_approved_layout', 'client_approved_finishes', 'client_decided_at', 'client_decided_by',
        'client_comment', 'technical_approved_at', 'technical_approved_by', 'technical_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DesignStatus::class,
            'number' => 'integer',
            'sent_at' => 'datetime',
            'client_approved_layout' => 'boolean',
            'client_approved_finishes' => 'boolean',
            'client_decided_at' => 'datetime',
            'technical_approved_at' => 'datetime',
        ];
    }

    public function isEditableInPlace(): bool
    {
        return $this->status === DesignStatus::Draft;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Measurement, $this> */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function technicalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function clientDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_decided_by');
    }
}
