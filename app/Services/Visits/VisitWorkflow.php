<?php

namespace App\Services\Visits;

use App\Enums\LeadStatus;
use App\Enums\VisitCreditStatus;
use App\Enums\VisitStatus;
use App\Models\CalendarBooking;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitCredit;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida operativo de una visita. Contrato para projects (app de
 * técnico): start(), complete() y markNoShow() son idempotentes.
 */
class VisitWorkflow
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly VisitBookingService $booking,
        private readonly PaymentService $payments,
        private readonly LeadBridge $leads,
        private readonly ActivityLogger $audit,
        private readonly VisitNotifier $notifier,
    ) {}

    public function start(SiteVisit $visit, User $actor): SiteVisit
    {
        return $this->move($visit, $actor, VisitStatus::InProgress, [VisitStatus::Scheduled, VisitStatus::Confirmed], 'visita.iniciada', ['started_at' => now()]);
    }

    public function complete(SiteVisit $visit, User $actor): SiteVisit
    {
        $visit = $this->move($visit, $actor, VisitStatus::Completed, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress], 'visita.realizada', ['completed_at' => now()]);

        if ($visit->status === VisitStatus::Completed) {
            $this->leads->advance($visit->lead_id ? Lead::query()->find($visit->lead_id) : null, LeadStatus::VisitDone, $actor, "Visita {$visit->folio} realizada.");
        }

        return $visit;
    }

    public function markNoShow(SiteVisit $visit, User $actor, ?string $reason = null): SiteVisit
    {
        return $this->move($visit, $actor, VisitStatus::NoShow, [VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress], 'visita.cliente_ausente', [
            'internal_notes' => $reason ? trim(($visit->internal_notes ? $visit->internal_notes."\n" : '').'Cliente ausente: '.$reason) : $visit->internal_notes,
        ]);
    }

    /** Confirmación con el cliente (p. ej. llamada de confirmación). */
    public function confirm(SiteVisit $visit, User $actor): SiteVisit
    {
        return $this->move($visit, $actor, VisitStatus::Confirmed, [VisitStatus::Scheduled], 'visita.confirmada');
    }

    /**
     * Cancela la visita y libera el horario. El reembolso (opcional) usa
     * PaymentService::refund (exige payments.refund).
     */
    public function cancel(SiteVisit $visit, User $actor, string $reason): SiteVisit
    {
        return DB::transaction(function () use ($visit, $actor, $reason) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === VisitStatus::Cancelled) {
                return $locked;
            }

            if ($locked->status->isFinal() || $locked->status === VisitStatus::InProgress) {
                throw new VisitBookingException('Esta visita ya no se puede cancelar ('.$locked->status->label().').');
            }

            $from = $locked->status;

            if ($booking = $locked->activeBooking()) {
                $this->bookings->release($booking);
            }

            if ($from->isAwaitingPayment() && ($payment = $locked->payment) !== null) {
                $this->payments->cancel($payment, 'Visita cancelada', $actor);
            }

            $locked->forceFill([
                'status' => VisitStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 200),
                'hold_expires_at' => null,
                'attention_reason' => null,
                'attention_at' => null,
            ])->save();

            $this->audit->log('visita.cancelada', $locked, ['folio' => $locked->folio, 'de' => $from->value, 'motivo' => $reason]);

            return $locked;
        });
    }

    /**
     * Reprograma a un nuevo inicio (misma duración congelada) liberando el
     * horario anterior en la MISMA transacción: si el nuevo no se puede
     * apartar, nada cambia.
     *
     * @throws SlotUnavailableException|VisitBookingException
     */
    public function reschedule(SiteVisit $visit, CarbonImmutable $startsAt, ?int $technicianId, User $actor): SiteVisit
    {
        $visit = DB::transaction(function () use ($visit, $startsAt, $technicianId) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isUpcoming() && ! ($locked->status->isAwaitingPayment() && $locked->holdIsActive())) {
                throw new VisitBookingException('Solo se pueden reprogramar visitas agendadas o con apartado vigente.');
            }

            $policy = $locked->policy;
            $zone = $locked->zone;
            $duration = (int) $locked->starts_at->diffInMinutes($locked->ends_at);
            $endsAt = $startsAt->addMinutes($duration);
            $before = ['starts_at' => $locked->starts_at->toIso8601String(), 'technician_id' => $locked->technician_id];

            if ($old = $locked->activeBooking()) {
                $this->bookings->release($old);
            }

            $hold = $locked->status->isAwaitingPayment() && $locked->hold_expires_at !== null ? CarbonImmutable::parse($locked->hold_expires_at) : null;
            $booking = $this->booking->reserveBestTechnician($zone, $policy, $startsAt, $endsAt, $hold, $locked, $technicianId, true);

            $locked->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'technician_id' => $booking->user_id,
                'attention_reason' => null,
                'attention_at' => null,
                'reminder_sent_at' => null,
                'status' => $locked->status === VisitStatus::Confirmed ? VisitStatus::Scheduled : $locked->status,
            ])->save();

            $this->audit->log('visita.reprogramada', $locked, [
                'before' => $before,
                'after' => ['starts_at' => $startsAt->toIso8601String(), 'technician_id' => $booking->user_id],
            ]);

            return $locked;
        });

        if ($visit->status->isUpcoming()) {
            $this->notifier->assigned($visit, 'Visita reprogramada');
        }

        return $visit;
    }

    /**
     * Asigna/reasigna técnico en el mismo horario (re-reserva atómica).
     *
     * @throws SlotUnavailableException|VisitBookingException
     */
    public function assign(SiteVisit $visit, User $technician, User $actor): SiteVisit
    {
        if ($visit->technician_id === $technician->id) {
            return $visit;
        }

        $visit = DB::transaction(function () use ($visit, $technician) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isFinal() || $locked->status === VisitStatus::InProgress) {
                throw new VisitBookingException('No se puede reasignar una visita '.mb_strtolower($locked->status->label()).'.');
            }

            $before = $locked->technician_id;

            if ($old = $locked->activeBooking()) {
                $this->bookings->release($old);
            }

            $hold = $locked->status->isAwaitingPayment() && $locked->hold_expires_at !== null ? CarbonImmutable::parse($locked->hold_expires_at) : null;
            $booking = $this->booking->reserveBestTechnician(
                $locked->zone,
                $locked->policy,
                CarbonImmutable::parse($locked->starts_at),
                CarbonImmutable::parse($locked->ends_at),
                $hold,
                $locked,
                $technician->id,
                true,
            );

            $locked->forceFill(['technician_id' => $booking->user_id])->save();

            $this->audit->log('visita.tecnico_asignado', $locked, ['before' => ['technician_id' => $before], 'after' => ['technician_id' => $booking->user_id]]);

            return $locked;
        });

        if ($visit->status->isUpcoming()) {
            $this->notifier->assigned($visit);
        }

        return $visit;
    }

    /**
     * @param  list<VisitStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function move(SiteVisit $visit, User $actor, VisitStatus $to, array $from, string $action, array $attributes = []): SiteVisit
    {
        return DB::transaction(function () use ($visit, $actor, $to, $from, $action, $attributes) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $to) {
                return $locked;
            }

            // Idempotencia hacia adelante: completar una visita ya completada, etc.
            if ($to === VisitStatus::InProgress && $locked->status === VisitStatus::Completed) {
                return $locked;
            }

            if (! in_array($locked->status, $from, true)) {
                throw new VisitBookingException("No se puede pasar de «{$locked->status->label()}» a «{$to->label()}».");
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => $to, ...$attributes])->save();

            $this->audit->log($action, $locked, ['before' => ['status' => $previous->value], 'after' => ['status' => $to->value], 'actor' => $actor->id]);

            return $locked;
        });
    }

    /** Booking vigente (para la vista de detalle). */
    public function bookingOf(SiteVisit $visit): ?CalendarBooking
    {
        return $visit->activeBooking();
    }

    /** ¿El crédito sigue disponible? (informativo para el panel) */
    public function creditIsAvailable(SiteVisit $visit): bool
    {
        return VisitCredit::query()->where('site_visit_id', $visit->id)->where('status', VisitCreditStatus::Available->value)->exists();
    }
}
