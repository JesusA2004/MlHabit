<?php

namespace App\Payments\Testing;

use App\Payments\ChargeRequest;
use App\Payments\ChargeResult;
use App\Payments\ChargeStatus;
use App\Payments\ConnectivityResult;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\Openpay\OpenpayErrorMap;
use App\Payments\Openpay\OpenpayPaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\RefundResult;
use App\Support\Money;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SOLO PARA PRUEBAS E2E (Playwright). Simula el sandbox de Openpay sin red:
 * imita el comportamiento de las tarjetas de prueba oficiales
 * (docs/pagos/OPENPAY-INVESTIGACION.md §10) a partir del token que entrega
 * el stub de openpay.js de tests/e2e (`tok_e2e_<últimos 4>`):
 *
 *   4111 1111 1111 1111 → completed
 *   4000 0000 0000 0002 → rechazo 3001
 *   4222 2222 2222 2220 → rechazo 3001
 *
 * Las transacciones se guardan en la caché `file` para que getCharge /
 * findChargeByOrderId / refund (webhook, conciliación, reembolsos) vean el
 * mismo estado entre peticiones. Se registra únicamente desde
 * App\Providers\E2eServiceProvider (APP_ENV=e2e + OPENPAY_E2E_FAKE=true);
 * nunca en producción.
 */
class E2eSandboxGateway implements PaymentGateway
{
    private const PREFIX = 'e2e-openpay:';

    private const APPROVED = ['1111'];

    private const DECLINED = ['0002', '2220'];

    public function __construct(private readonly OpenpayConfig $config)
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eSandboxGateway no puede usarse en producción.');
        }
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCardCharge(ChargeRequest $request): ChargeResult
    {
        if (! preg_match('/^tok_e2e_(\d{4})$/', $request->tokenId, $m)) {
            return $this->declined(2004, 400);
        }

        $last4 = $m[1];

        if ($this->findStored($request->orderId) !== null) {
            return new ChargeResult(status: ChargeStatus::DuplicateOrder, errorCode: '1006', httpStatus: 409);
        }

        if (in_array($last4, self::DECLINED, true) || ! in_array($last4, self::APPROVED, true)) {
            return $this->declined(3001, 402);
        }

        $transaction = [
            'id' => 'tre2e'.Str::lower(Str::random(15)),
            'order_id' => $request->orderId,
            'status' => 'completed',
            'amount' => $request->amount->amount,
            'authorization' => (string) random_int(100000, 999999),
            'card' => ['brand' => 'visa', 'card_number' => '411111XXXXXX'.$last4],
            'method' => 'card',
        ];

        $this->store($transaction);

        return $this->parse($transaction, 200);
    }

    public function getCharge(string $transactionId, ?string $customerIp = null): ChargeResult
    {
        $transaction = $this->cache()->get(self::PREFIX.'tx:'.$transactionId);

        return is_array($transaction) ? $this->parse($transaction, 200) : ChargeResult::notFound();
    }

    public function findChargeByOrderId(string $orderId, ?string $customerIp = null): ChargeResult
    {
        $transaction = $this->findStored($orderId);

        return $transaction !== null ? $this->parse($transaction, 200) : ChargeResult::notFound();
    }

    public function refund(string $transactionId, Money $amount, string $description, ?string $customerIp = null): RefundResult
    {
        $transaction = $this->cache()->get(self::PREFIX.'tx:'.$transactionId);

        if (! is_array($transaction) || $transaction['status'] !== 'completed') {
            return new RefundResult(RefundResult::FAILED, errorCode: '1001', safeMessage: OpenpayErrorMap::message(1001), httpStatus: 400);
        }

        $refundId = 'tre2er'.Str::lower(Str::random(14));
        $previous = Money::of((string) ($transaction['refund']['amount'] ?? '0.00'));
        $refunded = $previous->plus($amount);

        $transaction['refund'] = ['id' => $refundId, 'amount' => $refunded->amount];
        if ($refunded->compare(Money::of((string) $transaction['amount'])) >= 0) {
            $transaction['status'] = 'refunded';
        }

        $this->store($transaction);

        return new RefundResult(RefundResult::COMPLETED, refundId: $refundId, httpStatus: 200);
    }

    public function ping(): ConnectivityResult
    {
        return new ConnectivityResult(true, 'Pasarela simulada de pruebas E2E (sin conexión a Openpay).', 200, null, 0);
    }

    private function declined(int $code, int $http): ChargeResult
    {
        return new ChargeResult(
            status: ChargeStatus::Declined,
            errorCode: (string) $code,
            safeMessage: OpenpayErrorMap::message($code),
            httpStatus: $http,
        );
    }

    /** @param array<string, mixed> $transaction */
    private function parse(array $transaction, int $http): ChargeResult
    {
        return (new OpenpayPaymentGateway($this->config))->parseTransaction($transaction, $http);
    }

    /** @param array<string, mixed> $transaction */
    private function store(array $transaction): void
    {
        $this->cache()->forever(self::PREFIX.'tx:'.$transaction['id'], $transaction);
        $this->cache()->forever(self::PREFIX.'order:'.$transaction['order_id'], $transaction['id']);
    }

    /** @return array<string, mixed>|null */
    private function findStored(string $orderId): ?array
    {
        $id = $this->cache()->get(self::PREFIX.'order:'.$orderId);
        $transaction = is_string($id) ? $this->cache()->get(self::PREFIX.'tx:'.$id) : null;

        return is_array($transaction) ? $transaction : null;
    }

    private function cache(): Repository
    {
        return Cache::store('file');
    }
}
