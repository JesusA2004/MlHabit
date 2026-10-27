<?php

namespace App\Enums;

/**
 * Versión de diseño. La aprobación del cliente (layout + acabados) es
 * distinta de la aprobación técnica interna, que se exige antes de
 * fabricar (`technical_approved_at`).
 */
enum DesignStatus: string
{
    case Draft = 'draft';
    case SentToClient = 'sent_to_client';
    case ClientApproved = 'client_approved';
    case ChangesRequested = 'changes_requested';
    case TechnicallyApproved = 'technically_approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::SentToClient => 'En revisión del cliente',
            self::ClientApproved => 'Aprobado por el cliente',
            self::ChangesRequested => 'Cambios solicitados',
            self::TechnicallyApproved => 'Aprobado técnicamente',
            self::Superseded => 'Reemplazado',
        };
    }

    /** Texto para el cliente. */
    public function clientLabel(): string
    {
        return match ($this) {
            self::SentToClient => 'Esperando tu revisión',
            self::ClientApproved, self::TechnicallyApproved => 'Aprobado',
            self::ChangesRequested => 'Pediste cambios',
            self::Superseded => 'Versión anterior',
            self::Draft => 'En preparación',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::SentToClient => 'info',
            self::ClientApproved => 'brand',
            self::ChangesRequested => 'warning',
            self::TechnicallyApproved => 'success',
            self::Superseded => 'neutral',
        };
    }

    public function isVisibleToClient(): bool
    {
        return $this !== self::Draft;
    }

    /** @return array{value: string, label: string, tone: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'tone' => $this->tone()];
    }
}
