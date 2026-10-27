<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Categoría de incidencia. */
enum IncidentCategory: string
{
    use HasOptions;

    case FabricationError = 'fabrication_error';
    case MissingPart = 'missing_part';
    case Damage = 'damage';
    case DesignError = 'design_error';
    case ClientChange = 'client_change';
    case InstallationError = 'installation_error';
    case SiteCondition = 'site_condition';

    public function label(): string
    {
        return match ($this) {
            self::FabricationError => 'Error de fabricación',
            self::MissingPart => 'Pieza faltante',
            self::Damage => 'Daño',
            self::DesignError => 'Error de diseño',
            self::ClientChange => 'Cambio del cliente',
            self::InstallationError => 'Error de instalación',
            self::SiteCondition => 'Condición del sitio',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::FabricationError, self::Damage, self::InstallationError => 'danger',
            self::MissingPart, self::DesignError => 'warning',
            self::ClientChange => 'info',
            self::SiteCondition => 'neutral',
        };
    }
}
