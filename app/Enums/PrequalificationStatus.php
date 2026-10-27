<?php

namespace App\Enums;

/**
 * Precalificación para pago a plazos. Revisión SIEMPRE manual; nunca hay
 * decisiones automáticas ni se comunica como "crédito aprobado".
 */
enum PrequalificationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case InfoRequested = 'info_requested';
    case Prequalified = 'prequalified';
    case NotPrequalified = 'not_prequalified';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Pendiente de enviar',
            self::Submitted => 'Recibida',
            self::InReview => 'En revisión',
            self::InfoRequested => 'Información solicitada',
            self::Prequalified => 'Precalificado (sujeto a evaluación final)',
            self::NotPrequalified => 'No precalificado por ahora',
            self::Withdrawn => 'Retirada',
        };
    }

    /** Texto para el cliente (nunca promete aprobación). */
    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft => 'Esperando tu información',
            self::Submitted => 'Recibimos tu solicitud',
            self::InReview => 'Estamos revisando tu solicitud',
            self::InfoRequested => 'Necesitamos un poco más de información',
            self::Prequalified => 'Precalificado, sujeto a evaluación final',
            self::NotPrequalified => 'Por ahora no es posible ofrecerte plazos',
            self::Withdrawn => 'Solicitud retirada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'brand',
            self::InReview => 'info',
            self::InfoRequested => 'warning',
            self::Prequalified => 'success',
            self::NotPrequalified => 'danger',
            self::Withdrawn => 'neutral',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::InReview, self::InfoRequested, self::Withdrawn],
            self::InReview => [self::InfoRequested, self::Prequalified, self::NotPrequalified, self::Withdrawn],
            self::InfoRequested => [self::Submitted, self::Withdrawn],
            self::Prequalified, self::NotPrequalified => [self::InReview],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** ¿El cliente puede (re)enviar información en este estado? */
    public function acceptsCustomerInput(): bool
    {
        return in_array($this, [self::Draft, self::InfoRequested], true);
    }

    public function isDecided(): bool
    {
        return in_array($this, [self::Prequalified, self::NotPrequalified], true);
    }
}
