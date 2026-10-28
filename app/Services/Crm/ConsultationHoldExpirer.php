<?php

namespace App\Services\Crm;

use App\Enums\BookingStatus;
use App\Enums\ConsultationStatus;
use App\Enums\LeadActivityType;
use App\Enums\PaymentStatus;
use App\Models\CalendarBooking;
use App\Models\Consultation;
use App\Models\Payment;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Services\Scheduling\BookingService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Vence apartados de asesorías sin pagar: concilia primero si había un cobro
 * en curso (nunca se cancela una asesoría cuyo pago sí entró), cancela la
 * intención de cobro (ya no se podrá cobrar), libera el horario y marca la
 * asesoría como cancelada ("Apartado vencido sin pago").
 */
class ConsultationHoldExpirer
{
    public const REASON = 'Apartado vencido sin pago';

    public function __construct(
        private readonly PaymentService $payments,
        private readonly BookingService $bookings,
        private readonly LeadWorkflow $workflow,
        private readonly ActivityLogger $audit,
    ) {}

    public function expireDue(): int
    {
        $count = 0;

        Consultation::query()
            ->where('status', ConsultationStatus::PendingPayment->value)
            ->where('hold_expires_at', '<=', now())
            ->orderBy('id')
            ->get()
            ->each(function (Consultation $consultation) use (&$count) {
                if ($this->expire($consultation)) {
                    $count++;
                }
            });

        return $count;
    }

    public function expire(Consultation $consultation): bool
    {
        $consultation = $consultation->fresh() ?? $consultation;

        if (! $consultation->status->isAwaitingPayment() || $consultation->hold_expires_at === null || $consultation->hold_expires_at->isFuture()) {
            return false;
        }

        $payment = $consultation->payment_id !== null ? Payment::query()->find($consultation->payment_id) : null;

        if ($payment !== null && $payment->status === PaymentStatus::Processing) {
            try {
                $payment = $this->payments->reconcile($payment);
            } catch (Throwable) {
                return false;
            }
            if (! in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Pending], true)) {
                // Pagado (lo atiende el listener) o aún en 3DS/conciliación: no se vence todavía.
                return false;
            }
        }

        return DB::transaction(function () use ($consultation) {
            /** @var Consultation $locked */
            $locked = Consultation::query()->whereKey($consultation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isAwaitingPayment()) {
                return false;
            }

            $payment = $locked->payment_id !== null ? Payment::query()->find($locked->payment_id) : null;
            if ($payment !== null && ! $this->payments->cancel($payment, 'Apartado de asesoría vencido')) {
                return false;
            }

            CalendarBooking::query()
                ->where('bookable_type', $locked->getMorphClass())
                ->where('bookable_id', $locked->id)
                ->where('status', '!=', BookingStatus::Released->value)
                ->get()
                ->each(fn (CalendarBooking $b) => $this->bookings->release($b));

            $locked->forceFill([
                'status' => ConsultationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => self::REASON,
                'hold_expires_at' => null,
            ])->save();

            $this->workflow->record($locked->lead, LeadActivityType::ConsultationUpdated, 'Asesoría liberada: el apartado venció sin pago.', null, ['consultation_id' => $locked->id]);
            $this->audit->log('asesoria.apartado_vencido', $locked, ['folio' => $locked->folio]);

            return true;
        });
    }
}
