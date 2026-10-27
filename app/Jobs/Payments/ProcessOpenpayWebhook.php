<?php

namespace App\Jobs\Payments;

use App\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\WebhookEvent;
use App\Payments\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un webhook de Openpay: localiza el pago (por id de transacción o
 * por el order_id `{order_id}-{n}`), RE-CONSULTA la transacción en el API y
 * converge el estado con PaymentService::reconcile(). El payload nunca se
 * usa como prueba de pago. Transacciones desconocidas quedan `ignored`.
 */
class ProcessOpenpayWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(PaymentService $payments): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || in_array($event->status, [WebhookEventStatus::Processed, WebhookEventStatus::Ignored], true)) {
            return;
        }

        $event->forceFill(['attempts' => $event->attempts + 1])->save();

        if ($event->transaction_id === null) {
            $this->finish($event, WebhookEventStatus::Ignored, 'Evento sin transacción de cargo.');

            return;
        }

        $payment = $this->locatePayment($event);

        if ($payment === null) {
            $this->finish($event, WebhookEventStatus::Ignored, 'Transacción desconocida para ML Habitat.');

            return;
        }

        try {
            $payments->reconcile($payment, $event->transaction_id);
            $this->finish($event, WebhookEventStatus::Processed, null);
        } catch (Throwable $e) {
            $event->forceFill(['status' => WebhookEventStatus::Failed, 'error' => mb_substr('Error al conciliar: '.class_basename($e), 0, 500)])->save();
            Log::error('openpay.webhook.process_failed', ['event' => $event->id, 'error' => class_basename($e)]);

            throw $e;
        }
    }

    private function locatePayment(WebhookEvent $event): ?Payment
    {
        $txId = (string) $event->transaction_id;

        $payment = Payment::query()->where('provider_transaction_id', $txId)->first();
        if ($payment !== null) {
            return $payment;
        }

        $attempt = PaymentAttempt::query()->where('provider_transaction_id', $txId)->first();
        if ($attempt !== null) {
            return Payment::query()->find($attempt->payment_id);
        }

        $orderId = $event->payload['transaction']['order_id'] ?? null;
        $parsed = Payment::parseAttemptOrderId(is_string($orderId) ? $orderId : null);

        return $parsed !== null ? Payment::query()->where('order_id', $parsed['order_id'])->first() : null;
    }

    private function finish(WebhookEvent $event, WebhookEventStatus $status, ?string $note): void
    {
        $event->forceFill(['status' => $status, 'error' => $note, 'processed_at' => now()])->save();
    }

    public function failed(?Throwable $e): void
    {
        WebhookEvent::query()->whereKey($this->webhookEventId)->update([
            'status' => WebhookEventStatus::Failed->value,
            'error' => 'No se pudo conciliar tras varios intentos; payments:reconcile lo reintentará.',
        ]);
    }
}
