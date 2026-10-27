<?php

namespace App\Enums;

/**
 * Contrato. `deposit_refunded`: el anticipo que lo activó fue reembolsado;
 * el proyecto NO retrocede solo (puede haber fabricación en curso): el
 * equipo decide si cobra de nuevo o cancela. Ver docs/PROYECTOS.md.
 */
enum ContractStatus: string
{
    case PendingDeposit = 'pending_deposit';
    case Active = 'active';
    case DepositRefunded = 'deposit_refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingDeposit => 'Pendiente de anticipo',
            self::Active => 'Activo',
            self::DepositRefunded => 'Anticipo reembolsado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingDeposit => 'warning',
            self::Active => 'success',
            self::DepositRefunded => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    /** @return array{value: string, label: string, tone: string} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label(), 'tone' => $this->tone()];
    }
}
