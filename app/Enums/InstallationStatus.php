<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Orden de instalación. */
enum InstallationStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case EvidenceSubmitted = 'evidence_submitted';
    case ClientReview = 'client_review';
    case QualityReview = 'quality_review';
    case Approved = 'approved';
    case PaymentAuthorized = 'payment_authorized';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programada',
            self::InProgress => 'En curso',
            self::EvidenceSubmitted => 'Evidencia enviada',
            self::ClientReview => 'Revisión del cliente',
            self::QualityReview => 'Revisión de calidad',
            self::Approved => 'Aprobada',
            self::PaymentAuthorized => 'Pago autorizado',
            self::Paid => 'Pagada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'neutral',
            self::Scheduled, self::EvidenceSubmitted, self::QualityReview => 'info',
            self::InProgress => 'brand',
            self::ClientReview => 'warning',
            self::Approved, self::PaymentAuthorized, self::Paid => 'success',
        };
    }

    /** Paso del recorrido de la orden (para ProgressSteps). */
    public function step(): string
    {
        return match ($this) {
            self::Draft, self::Scheduled => 'scheduled',
            self::InProgress => 'in_progress',
            self::EvidenceSubmitted, self::ClientReview => 'client_review',
            self::QualityReview => 'quality_review',
            self::Approved, self::PaymentAuthorized, self::Paid, self::Cancelled => 'approved',
        };
    }

    /** ¿Aún se pueden editar partidas y precios (antes de iniciar)? */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }
}
