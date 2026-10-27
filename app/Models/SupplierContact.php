<?php

namespace App\Models;

use Database\Factories\SupplierContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $name
 * @property string|null $position
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_primary
 */
class SupplierContact extends Model
{
    /** @use HasFactory<SupplierContactFactory> */
    use HasFactory;

    protected $fillable = ['supplier_id', 'name', 'position', 'email', 'phone', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
