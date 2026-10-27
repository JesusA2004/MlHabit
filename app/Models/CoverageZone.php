<?php

namespace App\Models;

use Database\Factories\CoverageZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona operativa donde sí se pueden vender y atender visitas.
 *
 * @property int $id
 * @property string $name
 * @property string $state
 * @property string|null $municipality
 * @property bool $is_active
 * @property bool $visits_enabled
 * @property string|null $center_lat
 * @property string|null $center_lng
 * @property string|null $radius_km
 * @property int $travel_buffer_minutes
 * @property int|null $max_visits_per_day
 * @property string|null $notes
 */
class CoverageZone extends Model
{
    /** @use HasFactory<CoverageZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'state', 'municipality', 'is_active', 'visits_enabled', 'center_lat', 'center_lng',
        'radius_km', 'travel_buffer_minutes', 'max_visits_per_day', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'visits_enabled' => 'boolean',
            'center_lat' => 'decimal:7',
            'center_lng' => 'decimal:7',
            'radius_km' => 'decimal:2',
            'travel_buffer_minutes' => 'integer',
            'max_visits_per_day' => 'integer',
        ];
    }

    /** @return HasMany<CoveragePostalCode, $this> */
    public function postalCodes(): HasMany
    {
        return $this->hasMany(CoveragePostalCode::class);
    }

    /**
     * Técnicos que atienden visitas en la zona.
     *
     * @return BelongsToMany<User, $this>
     */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return BelongsToMany<Supplier, $this> */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class);
    }
}
