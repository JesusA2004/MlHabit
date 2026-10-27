<?php

namespace App\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $label
 * @property string $street
 * @property string $exterior_number
 * @property string|null $interior_number
 * @property string $neighborhood
 * @property string $postal_code
 * @property string $municipality
 * @property string $state
 * @property string|null $references
 * @property int|null $coverage_zone_id
 * @property-read string $one_line
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id', 'label', 'street', 'exterior_number', 'interior_number', 'neighborhood',
        'postal_code', 'municipality', 'state', 'references', 'coverage_zone_id',
    ];

    protected $appends = ['one_line'];

    public function getOneLineAttribute(): string
    {
        $number = $this->exterior_number.($this->interior_number ? ' int. '.$this->interior_number : '');

        return "{$this->street} {$number}, {$this->neighborhood}, C.P. {$this->postal_code}, {$this->municipality}, {$this->state}";
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CoverageZone, $this> */
    public function coverageZone(): BelongsTo
    {
        return $this->belongsTo(CoverageZone::class);
    }
}
