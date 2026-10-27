<?php

namespace App\Models;

use Database\Factories\SupplierEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evaluación 1–5 de un proveedor (calidad, puntualidad, comunicación).
 *
 * @property int $id
 * @property int $supplier_id
 * @property int|null $project_id
 * @property int $quality_score
 * @property int $timeliness_score
 * @property int $communication_score
 * @property string|null $comments
 * @property int|null $evaluated_by
 * @property Carbon|null $created_at
 */
class SupplierEvaluation extends Model
{
    /** @use HasFactory<SupplierEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id', 'project_id', 'quality_score', 'timeliness_score',
        'communication_score', 'comments', 'evaluated_by',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'integer',
            'timeliness_score' => 'integer',
            'communication_score' => 'integer',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
