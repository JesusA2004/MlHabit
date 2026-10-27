<?php

namespace App\Services\Visits;

use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Models\SiteVisit;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Services\Scheduling\BookingService;
use Illuminate\Support\Facades\DB;

/**
 * Vence apartados de visitas sin pagar: libera el horario, cancela la
 * intención de cobro (ya no se podrá cobrar) y marca la visita `expired`.
 * Antes concilia con Openpay si había un cobro en curso: nunca se vence una
 * visita cuyo pago sí entró.
 */
class VisitHoldExpirer
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly BookingService $bookings,
        private readonly ActivityLogger $audit,
    ) {}

    public function expireDue(): int
    {
        $count = 0;

        SiteVisit::query()
            ->whereIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value])
            ->where('hold_expires_at', '<=', now())
            ->orderBy('id')
            ->get()
            ->each(function (SiteVisit $visit) use (&$count) {
                if ($this->expire($visit)) {
                    $count++;
                }
            });

        return $count;
    }

    public function expire(SiteVisit $visit): bool
    {
        $visit = $visit->fresh() ?? $visit;

        if (! $visit->status->isAwaitingPayment() || $visit->hold_expires_at === null || $visit->hold_expires_at->isFuture()) {
            return false;
        }

        $payment = $visit->payment;

        if ($payment !== null && $payment->status === PaymentStatus::Processing) {
            $payment = $this->payments->reconcile($payment);
            if ($payment->status !== PaymentStatus::Failed && $payment->status !== PaymentStatus::Pending) {
                // Pagado (lo atiende el listener) o aún en 3DS/conciliación: no se vence todavía.
                return false;
            }
        }

        return DB::transaction(function () use ($visit) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isAwaitingPayment()) {
                return false;
            }

            if (($payment = $locked->payment) !== null && ! $this->payments->cancel($payment, 'Apartado de visita vencido')) {
                return false;
            }

            if ($booking = $locked->activeBooking()) {
                $this->bookings->release($booking);
            }

            $locked->forceFill(['status' => VisitStatus::Expired])->save();
            $this->audit->log('visita.apartado_vencido', $locked, ['folio' => $locked->folio]);

            return true;
        });
    }
}
