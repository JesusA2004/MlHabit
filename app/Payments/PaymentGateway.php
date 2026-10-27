<?php

namespace App\Payments;

use App\Payments\Openpay\OpenpayPaymentGateway;
use App\Support\Money;
use Illuminate\Container\Attributes\Bind;
use Illuminate\Container\Attributes\Singleton;

/**
 * Pasarela de cobro con tarjeta. La implementación real es Openpay (API REST
 * con el cliente Http de Laravel). En pruebas se sustituye con
 * `app()->instance(PaymentGateway::class, …)` o con Http::fake().
 *
 * Ningún método lanza excepciones por errores del proveedor: siempre
 * devuelve un resultado tipado con mensaje seguro en español.
 */
#[Bind(OpenpayPaymentGateway::class)]
#[Singleton]
interface PaymentGateway
{
    public function isConfigured(): bool;

    /** Crea un cargo con un token de Openpay.js (POST /charges). Nunca se reintenta automáticamente. */
    public function createCardCharge(ChargeRequest $request): ChargeResult;

    /** Consulta un cargo por id de transacción (GET /charges/{id}). */
    public function getCharge(string $transactionId, ?string $customerIp = null): ChargeResult;

    /** Busca el cargo más reciente con ese order_id (GET /charges?order_id=). NotFound si no existe. */
    public function findChargeByOrderId(string $orderId, ?string $customerIp = null): ChargeResult;

    /** Reembolso total o parcial (POST /charges/{id}/refund). */
    public function refund(string $transactionId, Money $amount, string $description, ?string $customerIp = null): RefundResult;

    /** Prueba de conectividad segura (GET /charges?limit=1). */
    public function ping(): ConnectivityResult;
}
