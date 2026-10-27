<?php

namespace App\Services;

use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;

/**
 * Cobertura OPERATIVA. Es la única fuente de verdad para vender visitas:
 * el texto publicitario de alcance del CMS no se consulta aquí.
 */
class CoverageService
{
    /** Zona activa que atiende un código postal, o null. */
    public function zoneForPostalCode(?string $postalCode): ?CoverageZone
    {
        $postalCode = preg_replace('/\D/', '', (string) $postalCode);
        if ($postalCode === null || strlen($postalCode) !== 5) {
            return null;
        }

        $zone = CoveragePostalCode::query()
            ->where('postal_code', $postalCode)
            ->with('zone')
            ->first()?->zone;

        return $zone && $zone->is_active ? $zone : null;
    }

    /** ¿Se pueden vender visitas en ese CP (zona activa, visitas habilitadas y técnicos asignados)? */
    public function acceptsVisits(?string $postalCode): bool
    {
        $zone = $this->zoneForPostalCode($postalCode);

        return $zone !== null
            && $zone->visits_enabled
            && $zone->technicians()->where('is_active', true)->exists();
    }
}
