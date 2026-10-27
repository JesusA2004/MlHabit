<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\MeasurementStatus;
use Database\Factories\MeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versión de medición de un espacio (desde la visita técnica). Una versión
 * enviada es inmutable: cualquier corrección crea una nueva versión con
 * `supersedes_id` apuntando a la anterior.
 *
 * Fotos y croquis: adjuntos privados con rol `photo` y `sketch`.
 *
 * @property int $id
 * @property int|null $site_visit_id
 * @property int|null $project_id
 * @property int $technician_id
 * @property int $version
 * @property MeasurementStatus $status
 * @property string|null $space_type
 * @property string|null $access_notes
 * @property string|null $existing_installations
 * @property string|null $general_notes
 * @property Carbon|null $recorded_at
 * @property Carbon|null $submitted_at
 * @property int|null $supersedes_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'site_visit_id', 'project_id', 'technician_id', 'version', 'status', 'space_type',
        'access_notes', 'existing_installations', 'general_notes', 'recorded_at', 'submitted_at', 'supersedes_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeasurementStatus::class,
            'version' => 'integer',
            'recorded_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === MeasurementStatus::Draft;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<SiteVisit, $this> */
    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** @return BelongsTo<Measurement, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Measurement::class, 'supersedes_id');
    }

    /** @return HasMany<MeasurementItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MeasurementItem::class)->orderBy('sort')->orderBy('id');
    }
}
