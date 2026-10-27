<?php

namespace App\Payments;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Refund;
use App\Models\WebhookEvent;

/**
 * Arreglos explícitos para Inertia (nunca el modelo completo): sin tokens,
 * sin payloads del proveedor, sin metadata interna.
 */
final class PaymentPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'folio' => $payment->folio,
            'orderId' => $payment->order_id,
            'concept' => $payment->concept->toArray(),
            'description' => $payment->description,
            'method' => $payment->method->toArray(),
            'provider' => $payment->provider->toArray(),
            'amount' => $payment->amount,
            'refundedAmount' => $payment->refunded_amount,
            'refundableAmount' => $payment->refundableMoney()->amount,
            'currency' => $payment->currency,
            'status' => $payment->status->toArray(),
            'cardBrand' => $payment->card_brand,
            'cardLast4' => $payment->card_last4,
            'authorization' => $payment->provider_authorization,
            'reference' => $payment->reference,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'createdAt' => $payment->created_at?->toIso8601String(),
            'errorMessage' => $payment->error_message,
            'customer' => $payment->relationLoaded('customer') ? [
                'name' => $payment->customer->full_name,
                'email' => $payment->customer->email,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function attempt(PaymentAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'number' => $attempt->number,
            'status' => $attempt->status->toArray(),
            'transactionId' => $attempt->provider_transaction_id,
            'httpStatus' => $attempt->http_status,
            'errorCode' => $attempt->error_code,
            'errorMessage' => $attempt->error_message,
            'createdAt' => $attempt->created_at?->toIso8601String(),
        ];
    }

    private const EVENT_LABELS = [
        'created' => 'Cobro creado',
        'attempt_started' => 'Intento de cobro enviado',
        'selective_3ds' => 'Reintento con 3D Secure (antifraude)',
        'requires_3ds' => 'Autenticación con el banco (3D Secure)',
        'pending' => 'Pendiente de confirmación',
        'outcome_unknown' => 'Sin respuesta concluyente: se conciliará',
        'attempt_expired' => 'Intento sin completar',
        'paid' => 'Pago confirmado',
        'failed' => 'Pago rechazado',
        'cancelled' => 'Cobro cancelado',
        'refund_requested' => 'Reembolso solicitado',
        'refunded' => 'Reembolso completado',
        'refund_failed' => 'Reembolso rechazado',
        'refund_unknown' => 'Reembolso por confirmar',
        'chargeback' => 'Contracargo recibido',
        'chargeback_update' => 'Actualización de contracargo',
        'chargeback_won' => 'Contracargo ganado',
        'duplicate_charge_detected' => 'Cargo duplicado detectado',
        'remote_state_mismatch' => 'Diferencia con Openpay',
    ];

    private const SOURCE_LABELS = [
        'api' => 'Openpay',
        'webhook' => 'Webhook',
        'reconcile' => 'Conciliación',
        'manual' => 'Manual',
        'system' => 'Sistema',
    ];

    /** @return array<string, mixed> */
    public static function event(PaymentEvent $event): array
    {
        $tone = match ($event->type) {
            'paid', 'refunded', 'chargeback_won' => 'success',
            'failed', 'refund_failed', 'chargeback', 'duplicate_charge_detected', 'remote_state_mismatch' => 'danger',
            'outcome_unknown', 'refund_unknown', 'requires_3ds', 'attempt_expired' => 'warning',
            default => 'neutral',
        };

        return [
            'id' => $event->id,
            'title' => self::EVENT_LABELS[$event->type] ?? $event->type,
            'description' => self::SOURCE_LABELS[$event->source] ?? $event->source,
            'date' => $event->created_at?->toIso8601String(),
            'actor' => $event->relationLoaded('user') ? $event->user?->name : null,
            'tone' => $tone,
        ];
    }

    /** @return array<string, mixed> */
    public static function refund(Refund $refund): array
    {
        return [
            'uuid' => $refund->uuid,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
            'status' => $refund->status->toArray(),
            'errorMessage' => $refund->error_message,
            'requestedBy' => $refund->relationLoaded('requester') ? $refund->requester?->name : null,
            'processedAt' => $refund->processed_at?->toIso8601String(),
            'createdAt' => $refund->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function webhook(WebhookEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->event_type,
            'status' => $event->status->toArray(),
            'transactionId' => $event->transaction_id,
            'error' => $event->error,
            'attempts' => $event->attempts,
            'receivedAt' => $event->received_at?->toIso8601String(),
            'processedAt' => $event->processed_at?->toIso8601String(),
        ];
    }
}
