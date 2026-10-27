<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviada',
            self::Accepted => 'Aceptada',
            self::Rejected => 'Rechazada',
            self::Expired => 'Vencida',
            self::Superseded => 'Reemplazada',
        };
    }

    public function clientLabel(): string
    {
        return match ($this) {
            self::Sent => 'Esperando tu respuesta',
            self::Accepted => 'Aceptada',
            self::Rejected => 'No aceptada',
            self::Expired => 'Vencida',
            self::Superseded => 'Versión anterior',
            self::Draft => 'En preparación',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'warning',
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
