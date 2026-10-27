<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Enums\TechnicianStatus;
use Database\Factories\TechnicianProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha operativa de un usuario con rol `tecnico`: a qué proveedor
 * pertenece (si aplica), si hace visitas y/o instalaciones, habilidades.
 * Documentos privados (identificación, constancias) vía HasAttachments.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $supplier_id
 * @property TechnicianStatus $status
 * @property bool $does_visits
 * @property bool $does_installations
 * @property string|null $skills
 * @property string|null $notes
 */
class TechnicianProfile extends Model
{
    /** @use HasFactory<TechnicianProfileFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = ['user_id', 'supplier_id', 'status', 'does_visits', 'does_installations', 'skills', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => TechnicianStatus::class,
            'does_visits' => 'boolean',
            'does_installations' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
