<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * Respuesta JSON estándar para el endpoint de cobro que consume
 * OpenpayCardForm.vue. Cualquier módulo que implemente su `chargeUrl`
 * (p. ej. anticipos en projects) debe responder con esta forma:
 *
 *   { status: 'paid'|'processing'|'redirect'|'failed', message, redirectUrl?, nextUrl? }
 *
 * `paid` solo se devuelve cuando el BACKEND confirmó el pago.
 */
final class ChargeResponse
{
    /**
     * @return array{status: string, message: string, redirectUrl: string|null, nextUrl: string|null}
     */
    public static function fromPayment(Payment $payment, ?string $nextUrl = null): array
    {
        return match (true) {
            $payment->status->isSettled() => [
                'status' => 'paid',
                'message' => 'Pago confirmado. Te enviamos el comprobante por correo.',
                'redirectUrl' => null,
                'nextUrl' => $nextUrl,
            ],
            $payment->status === PaymentStatus::Processing && $payment->redirect_url !== null => [
                'status' => 'redirect',
                'message' => 'Te llevaremos con tu banco para autorizar el pago.',
                'redirectUrl' => $payment->redirect_url,
                'nextUrl' => $nextUrl,
            ],
            $payment->status === PaymentStatus::Processing => [
                'status' => 'processing',
                'message' => 'Estamos confirmando tu pago con el banco. No cierres esta ventana ni vuelvas a intentarlo.',
                'redirectUrl' => null,
                'nextUrl' => $nextUrl,
            ],
            $payment->status === PaymentStatus::Cancelled => [
                'status' => 'failed',
                'message' => 'Esta solicitud de pago ya no está vigente. No se realizó ningún cargo.',
                'redirectUrl' => null,
                'nextUrl' => null,
            ],
            default => [
                'status' => 'failed',
                'message' => $payment->error_message ?: 'No se pudo completar el pago. No se realizó ningún cargo.',
                'redirectUrl' => null,
                'nextUrl' => null,
            ],
        };
    }

    /**
     * @return array{status: string, message: string, redirectUrl: null, nextUrl: string|null}
     */
    public static function fromException(PaymentException $e, ?string $nextUrl = null): array
    {
        return [
            'status' => match ($e->reason) {
                PaymentException::ALREADY_PAID => 'paid',
                PaymentException::IN_PROGRESS => 'processing',
                default => 'failed',
            },
            'message' => $e->getMessage(),
            'redirectUrl' => null,
            'nextUrl' => in_array($e->reason, [PaymentException::ALREADY_PAID, PaymentException::IN_PROGRESS], true) ? $nextUrl : null,
        ];
    }
}
