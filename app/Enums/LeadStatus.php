<?php

namespace App\Enums;

/**
 * Etapa comercial de un prospecto. El estado SOLO cambia mediante
 * App\Services\Crm\LeadWorkflow::transition(), que valida la transición,
 * registra la actividad del prospecto y la bitácora de auditoría.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case ConsultationScheduled = 'consultation_scheduled';
    case Prequalification = 'prequalification';
    case VisitPending = 'visit_pending';
    case VisitPaid = 'visit_paid';
    case VisitDone = 'visit_done';
    case Design = 'design';
    case Quoting = 'quoting';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Contacted => 'Contactado',
            self::ConsultationScheduled => 'Asesoría agendada',
            self::Prequalification => 'En precalificación',
            self::VisitPending => 'Visita por agendar',
            self::VisitPaid => 'Visita pagada',
            self::VisitDone => 'Visita realizada',
            self::Design => 'En diseño',
            self::Quoting => 'Cotizando',
            self::Negotiation => 'En negociación',
            self::Won => 'Ganado',
            self::Lost => 'Perdido',
            self::Cancelled => 'Cancelado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'brand',
            self::Contacted, self::ConsultationScheduled, self::Prequalification => 'info',
            self::VisitPending => 'warning',
            self::VisitPaid, self::VisitDone, self::Design, self::Quoting, self::Negotiation => 'info',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Cancelled => 'neutral',
        };
    }

    /** Orden del embudo (los estados de cierre van al final). */
    public function rank(): int
    {
        return match ($this) {
            self::New => 0,
            self::Contacted => 1,
            self::ConsultationScheduled => 2,
            self::Prequalification => 3,
            self::VisitPending => 4,
            self::VisitPaid => 5,
            self::VisitDone => 6,
            self::Design => 7,
            self::Quoting => 8,
            self::Negotiation => 9,
            self::Won, self::Lost, self::Cancelled => 10,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Cancelled], true);
    }

    /**
     * Transiciones manuales permitidas. El embudo no es lineal: un asesor
     * puede saltar etapas hacia adelante (p. ej. de contactado a visita) y
     * regresar una etapa si hubo un error; cualquier estado abierto puede
     * cerrarse como perdido o cancelado; uno perdido/cancelado puede
     * reabrirse como contactado.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        if ($this === self::Won) {
            return [];
        }

        if ($this === self::Lost || $this === self::Cancelled) {
            return [self::Contacted];
        }

        $forward = array_values(array_filter(
            self::cases(),
            fn (self $s) => ! $s->isClosed() && $s->rank() > $this->rank(),
        ));
        $back = array_values(array_filter(
            self::cases(),
            fn (self $s) => ! $s->isClosed() && $s !== self::New && $s->rank() === $this->rank() - 1,
        ));

        return [...$back, ...$forward, self::Won, self::Lost, self::Cancelled];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Estados que cuentan como "abiertos" en el embudo.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => ! $s->isClosed()));
    }
}
