<?php

namespace App\Services\Visits;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitCredit;
use App\Notifications\Visits\VisitAssignedNotification;
use App\Notifications\Visits\VisitPaymentLinkNotification;
use App\Notifications\Visits\VisitReminderNotification;
use App\Notifications\Visits\VisitScheduledNotification;
use App\Notifications\Visits\VisitStaffAlertNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Envía los avisos de visitas. Un fallo de correo nunca rompe el flujo de
 * pago/reserva: se registra y se continúa.
 */
class VisitNotifier
{
    public function visitPaid(SiteVisit $visit, ?string $attention): void
    {
        $visit->loadMissing(['customer', 'technician', 'payment']);
        $receipt = $visit->payment !== null ? $this->receiptUrl($visit->payment) : null;

        $this->toCustomer($visit->customer, new VisitScheduledNotification($visit, $receipt, $attention !== null));

        if ($attention === null && $visit->technician !== null) {
            $this->send($visit->technician, new VisitAssignedNotification($visit));
        }

        $this->toStaff(
            Permission::VisitsManage,
            new VisitStaffAlertNotification(
                $attention !== null ? 'Visita pagada que requiere atención' : 'Visita técnica pagada',
                $attention ?? ("{$visit->folio} · {$visit->customer->full_name}"),
                '/admin/visitas/'.$visit->uuid,
                urgent: $attention !== null,
            ),
        );
    }

    public function assigned(SiteVisit $visit, string $headline = 'Nueva visita asignada'): void
    {
        $visit->loadMissing('technician');
        if ($visit->technician !== null) {
            $this->send($visit->technician, new VisitAssignedNotification($visit, $headline));
        }
    }

    public function paymentLink(SiteVisit $visit, string $payUrl): void
    {
        $visit->loadMissing('customer');
        $this->toCustomer($visit->customer, new VisitPaymentLinkNotification($visit, $payUrl));
    }

    public function reminder(SiteVisit $visit): void
    {
        $visit->loadMissing(['customer', 'technician']);
        $this->toCustomer($visit->customer, new VisitReminderNotification($visit));
        if ($visit->technician !== null) {
            $this->send($visit->technician, new VisitReminderNotification($visit, forTechnician: true));
        }
    }

    public function creditRefundReview(VisitCredit $credit, Payment $payment, Refund $refund): void
    {
        $this->toStaff(Permission::PaymentsRefund, new VisitStaffAlertNotification(
            'Reembolso de una visita con crédito ya aplicado',
            "El pago {$payment->folio} se reembolsó ({$refund->amount}) pero su crédito de visita ya se aplicó a un contrato. Ajusta el saldo del anticipo con el cliente.",
            '/admin/pagos/'.$payment->uuid,
            urgent: true,
        ));
    }

    public function attention(SiteVisit $visit, string $reason): void
    {
        $this->toStaff(Permission::VisitsManage, new VisitStaffAlertNotification('Visita que requiere atención', "{$visit->folio}: {$reason}", '/admin/visitas/'.$visit->uuid, urgent: true));
    }

    public function receiptUrl(Payment $payment): string
    {
        return URL::signedRoute('pagos.comprobante', ['payment' => $payment->uuid]);
    }

    private function toCustomer(Customer $customer, Notification $notification): void
    {
        try {
            $user = $customer->user_id ? User::query()->find($customer->user_id) : null;

            if ($user !== null) {
                $user->notify($notification);
            } elseif ($customer->email) {
                Notifier::route('mail', $customer->email)->notify($notification);
            }
        } catch (Throwable $e) {
            Log::warning('visits.notify_customer_failed', ['error' => class_basename($e)]);
        }
    }

    private function toStaff(Permission $permission, Notification $notification): void
    {
        try {
            $staff = User::query()->withPermission($permission)->get()->filter(fn (User $u) => $u->isStaff());
            if ($staff->isNotEmpty()) {
                Notifier::send($staff, $notification);
            }
        } catch (Throwable $e) {
            Log::warning('visits.notify_staff_failed', ['error' => class_basename($e)]);
        }
    }

    private function send(User $user, Notification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('visits.notify_failed', ['error' => class_basename($e)]);
        }
    }
}
