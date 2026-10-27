<?php

namespace App\Enums;

/**
 * Flujo del proyecto. Las transiciones permitidas están definidas aquí y
 * las aplica ProjectWorkflow; ningún controlador escribe el estado directo.
 */
enum ProjectStatus: string
{
    case Preparation = 'preparation';
    case Measurement = 'measurement';
    case Design = 'design';
    case Quoting = 'quoting';
    case PendingDeposit = 'pending_deposit';
    case Authorized = 'authorized';
    case Fabrication = 'fabrication';
    case ReadyToInstall = 'ready_to_install';
    case InstallationScheduled = 'installation_scheduled';
    case Installing = 'installing';
    case QualityReview = 'quality_review';
    case PunchList = 'punch_list';
    case Completed = 'completed';
    case WarrantyService = 'warranty_service';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preparation => 'Preparación',
            self::Measurement => 'Medición',
            self::Design => 'Diseño',
            self::Quoting => 'Cotización',
            self::PendingDeposit => 'Pendiente de anticipo',
            self::Authorized => 'Autorizado',
            self::Fabrication => 'En fabricación',
            self::ReadyToInstall => 'Listo para instalar',
            self::InstallationScheduled => 'Instalación programada',
            self::Installing => 'Instalación en curso',
            self::QualityReview => 'Revisión de calidad',
            self::PunchList => 'Pendientes por atender',
            self::Completed => 'Completado',
            self::WarrantyService => 'Atención en garantía',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Texto para el cliente en el portal. */
    public function clientDescription(): string
    {
        return match ($this) {
            self::Preparation => 'Estamos preparando tu proyecto.',
            self::Measurement => 'Tomaremos medidas exactas de tu espacio.',
            self::Design => 'Nuestro equipo trabaja en el diseño de tu mueble.',
            self::Quoting => 'Preparamos tu cotización con base en el diseño.',
            self::PendingDeposit => 'Tu contrato está listo. Falta el anticipo para iniciar fabricación.',
            self::Authorized => 'Tu proyecto está autorizado y en cola de fabricación.',
            self::Fabrication => 'Tu mueble se está fabricando.',
            self::ReadyToInstall => 'Tu mueble está listo. Coordinaremos la instalación contigo.',
            self::InstallationScheduled => 'La instalación ya tiene fecha.',
            self::Installing => 'Estamos instalando tu mueble.',
            self::QualityReview => 'Revisamos la calidad de la instalación.',
            self::PunchList => 'Estamos atendiendo detalles pendientes.',
            self::Completed => 'Tu proyecto fue entregado.',
            self::WarrantyService => 'Estamos atendiendo una solicitud de garantía.',
            self::Cancelled => 'Este proyecto fue cancelado.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Cancelled => 'neutral',
            self::PendingDeposit, self::PunchList, self::WarrantyService => 'warning',
            self::QualityReview => 'info',
            default => 'brand',
        };
    }

    /** Etapa visible del recorrido del cliente (para la línea de tiempo). */
    public function journeyStep(): int
    {
        return match ($this) {
            self::Preparation, self::Measurement => 1,
            self::Design, self::Quoting => 2,
            self::PendingDeposit, self::Authorized => 3,
            self::Fabrication, self::ReadyToInstall => 4,
            self::InstallationScheduled, self::Installing => 5,
            self::QualityReview, self::PunchList => 6,
            self::Completed, self::WarrantyService => 7,
            self::Cancelled => 0,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Preparation => [self::Measurement, self::Design, self::Cancelled],
            self::Measurement => [self::Design, self::Cancelled],
            self::Design => [self::Measurement, self::Quoting, self::Cancelled],
            self::Quoting => [self::Design, self::PendingDeposit, self::Cancelled],
            self::PendingDeposit => [self::Authorized, self::Quoting, self::Cancelled],
            self::Authorized => [self::Fabrication, self::Cancelled],
            self::Fabrication => [self::ReadyToInstall, self::Cancelled],
            self::ReadyToInstall => [self::InstallationScheduled],
            self::InstallationScheduled => [self::Installing, self::ReadyToInstall],
            self::Installing => [self::QualityReview, self::PunchList],
            self::QualityReview => [self::Completed, self::PunchList],
            self::PunchList => [self::QualityReview, self::Installing],
            self::Completed => [self::WarrantyService],
            self::WarrantyService => [self::Completed],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isContracted(): bool
    {
        return $this->journeyStep() >= 3 && $this !== self::PendingDeposit;
    }
}
