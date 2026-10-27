<?php

namespace App\Payments\Openpay;

use App\Payments\ChargeRequest;
use App\Payments\ChargeResult;
use App\Payments\ChargeStatus;
use App\Payments\ConnectivityResult;
use App\Payments\PaymentGateway;
use App\Payments\RefundResult;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente REST de Openpay México con el Http de Laravel (sin SDK: ver
 * investigación §11). HTTP Basic con la llave privada, X-Forwarded-For con
 * la IP del cliente (obligatorio, §5), timeouts explícitos y reintentos
 * SOLO en consultas GET ante fallas de conexión. Un POST de cargo nunca se
 * reintenta: si no hay respuesta concluyente se devuelve Unknown y el
 * servicio concilia por order_id antes de permitir otro intento.
 *
 * Bitácora: solo operación, order_id, código HTTP, estado y código de
 * error. Nunca el cuerpo de la petición ni de la respuesta.
 */
class OpenpayPaymentGateway implements PaymentGateway
{
    private const AMOUNT_PLACEHOLDER = '__MLH_AMOUNT__';

    public function __construct(private readonly OpenpayConfig $config) {}

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function createCardCharge(ChargeRequest $request): ChargeResult
    {
        if (! $this->isConfigured()) {
            return new ChargeResult(status: ChargeStatus::Declined, errorCode: 'not_configured', safeMessage: OpenpayErrorMap::message(1002));
        }

        $payload = [
            'method' => 'card',
            'source_id' => $request->tokenId,
            'amount' => self::AMOUNT_PLACEHOLDER,
            'currency' => $request->currency,
            'description' => mb_substr($request->description, 0, 250),
            'order_id' => $request->orderId,
            'device_session_id' => $request->deviceSessionId,
            'customer' => array_filter([
                'name' => mb_substr($request->customer['name'], 0, 100),
                'last_name' => isset($request->customer['last_name']) ? mb_substr((string) $request->customer['last_name'], 0, 100) : null,
                'email' => $request->customer['email'],
                'phone_number' => $request->customer['phone_number'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        if ($request->use3ds) {
            $payload['use_3d_secure'] = true;
            if ($request->redirectUrl) {
                $payload['redirect_url'] = $request->redirectUrl;
            }
        }

        try {
            $response = $this->client($request->customerIp)
                ->withBody($this->encode($payload, $request->amount), 'application/json')
                ->post('charges');
        } catch (ConnectionException) {
            $this->log('charge', $request->orderId, null, ChargeStatus::Unknown, 'connection');

            return ChargeResult::unknown();
        } catch (Throwable $e) {
            $this->log('charge', $request->orderId, null, ChargeStatus::Unknown, class_basename($e));

            return ChargeResult::unknown();
        }

        $result = $response->successful()
            ? $this->parseTransaction((array) $response->json(), $response->status())
            : $this->parseError($response, forCharge: true);

        $this->log('charge', $request->orderId, $response->status(), $result->status, $result->errorCode);

        return $result;
    }

    public function getCharge(string $transactionId, ?string $customerIp = null): ChargeResult
    {
        try {
            $response = $this->readClient($customerIp)->get('charges/'.rawurlencode($transactionId));
        } catch (Throwable) {
            $this->log('get', null, null, ChargeStatus::Unknown, 'connection');

            return ChargeResult::unknown();
        }

        if ($response->status() === 404) {
            return ChargeResult::notFound();
        }

        $result = $response->successful()
            ? $this->parseTransaction((array) $response->json(), $response->status())
            : $this->parseError($response, forCharge: false);

        $this->log('get', $result->orderId, $response->status(), $result->status, $result->errorCode);

        return $result;
    }

    public function findChargeByOrderId(string $orderId, ?string $customerIp = null): ChargeResult
    {
        try {
            $response = $this->readClient($customerIp)->get('charges', ['order_id' => $orderId, 'limit' => 10]);
        } catch (Throwable) {
            $this->log('find', $orderId, null, ChargeStatus::Unknown, 'connection');

            return ChargeResult::unknown();
        }

        if (! $response->successful()) {
            $result = $this->parseError($response, forCharge: false);
            $this->log('find', $orderId, $response->status(), $result->status, $result->errorCode);

            return $result->status === ChargeStatus::NotFound ? $result : ChargeResult::unknown($response->status(), $result->errorCode);
        }

        $items = $response->json();
        $items = is_array($items) ? array_values(array_filter($items, fn ($t) => is_array($t) && ($t['order_id'] ?? null) === $orderId)) : [];

        if ($items === []) {
            $this->log('find', $orderId, $response->status(), ChargeStatus::NotFound, null);

            return ChargeResult::notFound();
        }

        // Preferimos un cargo completado; si no, el más reciente (orden desc. de Openpay).
        $chosen = collect($items)->first(fn (array $t) => strtolower((string) ($t['status'] ?? '')) === 'completed') ?? $items[0];
        $result = $this->parseTransaction($chosen, $response->status());
        $this->log('find', $orderId, $response->status(), $result->status, $result->errorCode);

        return $result;
    }

    public function refund(string $transactionId, Money $amount, string $description, ?string $customerIp = null): RefundResult
    {
        if (! $this->isConfigured()) {
            return new RefundResult(RefundResult::FAILED, errorCode: 'not_configured', safeMessage: 'Openpay no está configurado.');
        }

        try {
            $response = $this->client($customerIp)
                ->withBody($this->encode(['description' => mb_substr($description, 0, 250), 'amount' => self::AMOUNT_PLACEHOLDER], $amount), 'application/json')
                ->post('charges/'.rawurlencode($transactionId).'/refund');
        } catch (Throwable) {
            Log::warning('openpay.refund', ['transaction_id' => $transactionId, 'outcome' => 'unknown']);

            return new RefundResult(RefundResult::UNKNOWN, safeMessage: 'No hubo respuesta de Openpay. El reembolso quedó por confirmar; se conciliará automáticamente.');
        }

        $data = (array) $response->json();

        if ($response->successful()) {
            $refund = is_array($data['refund'] ?? null) ? $data['refund'] : [];
            Log::info('openpay.refund', ['transaction_id' => $transactionId, 'http' => $response->status(), 'outcome' => 'completed']);

            return new RefundResult(RefundResult::COMPLETED, refundId: isset($refund['id']) ? (string) $refund['id'] : null, httpStatus: $response->status());
        }

        $code = isset($data['error_code']) ? (string) $data['error_code'] : null;
        Log::warning('openpay.refund', ['transaction_id' => $transactionId, 'http' => $response->status(), 'error_code' => $code]);

        if (OpenpayErrorMap::isUncertain($code, $response->status())) {
            return new RefundResult(RefundResult::UNKNOWN, errorCode: $code, safeMessage: 'Openpay no confirmó el reembolso. Quedó por confirmar y se conciliará automáticamente.', httpStatus: $response->status());
        }

        return new RefundResult(
            RefundResult::FAILED,
            errorCode: $code,
            safeMessage: 'Openpay rechazó el reembolso'.($code ? " (código {$code})" : '').'. Revisa el monto o intenta desde el panel de Openpay.',
            httpStatus: $response->status(),
        );
    }

    public function ping(): ConnectivityResult
    {
        if (! $this->isConfigured()) {
            return new ConnectivityResult(false, 'Faltan variables de Openpay en el servidor.');
        }

        $started = hrtime(true);

        try {
            $response = $this->client(null)->get('charges', ['limit' => 1]);
        } catch (Throwable) {
            return new ConnectivityResult(false, 'No hubo conexión con Openpay (red o tiempo de espera).');
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        if ($response->successful()) {
            return new ConnectivityResult(true, 'Conexión correcta: Openpay aceptó las credenciales.', $response->status(), null, $latency);
        }

        $code = (string) ($response->json('error_code') ?? '');

        $message = match ((int) $code) {
            1002 => 'Credenciales rechazadas: revisa la llave privada y el ambiente (sandbox/producción).',
            1005 => 'El merchant id no existe en este ambiente.',
            1008, 1014 => 'La cuenta de Openpay está inactiva o desactivada.',
            1010 => 'Se configuró una llave pública donde se requiere la privada.',
            default => 'Openpay respondió con un error.',
        };

        return new ConnectivityResult(false, $message, $response->status(), $code !== '' ? $code : null, $latency);
    }

    private function client(?string $customerIp): PendingRequest
    {
        return Http::baseUrl($this->config->apiBase())
            ->withBasicAuth($this->config->privateKey(), '')
            ->acceptJson()
            ->connectTimeout($this->config->connectTimeout())
            ->timeout($this->config->timeout())
            ->withHeaders(['X-Forwarded-For' => $this->ip($customerIp)]);
    }

    /** Las lecturas son seguras de repetir: 2 reintentos solo ante fallas de conexión. */
    private function readClient(?string $customerIp): PendingRequest
    {
        return $this->client($customerIp)->retry(3, 250, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function ip(?string $ip): string
    {
        $ip = $ip ?: request()->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) ? (string) $ip : '127.0.0.1';
    }

    /**
     * JSON con el monto como número decimal exacto ("1500.00" → 1500.00) sin
     * pasar por float.
     *
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload, Money $amount): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return str_replace('"'.self::AMOUNT_PLACEHOLDER.'"', $amount->amount, $json);
    }

    /** @param array<string, mixed> $t */
    public function parseTransaction(array $t, ?int $httpStatus = null): ChargeResult
    {
        $raw = strtolower((string) ($t['status'] ?? ''));

        $status = match ($raw) {
            'completed' => ChargeStatus::Completed,
            'charge_pending', 'in_progress' => ChargeStatus::Pending,
            'failed' => ChargeStatus::Failed,
            'cancelled' => ChargeStatus::Cancelled,
            'refunded' => ChargeStatus::Refunded,
            'chargeback_pending' => ChargeStatus::ChargebackPending,
            'chargeback_accepted' => ChargeStatus::ChargebackAccepted,
            'chargeback_adjustment' => ChargeStatus::ChargebackAdjustment,
            default => ChargeStatus::Unknown,
        };

        $card = is_array($t['card'] ?? null) ? $t['card'] : [];
        $digits = preg_replace('/\D/', '', (string) ($card['card_number'] ?? '')) ?? '';
        $paymentMethod = is_array($t['payment_method'] ?? null) ? $t['payment_method'] : [];
        $refund = is_array($t['refund'] ?? null) ? $t['refund'] : [];
        $fee = is_array($t['fee'] ?? null) ? $t['fee'] : [];

        $errorCode = null;
        $safe = null;
        if ($status->isFailure()) {
            $errorCode = isset($t['error_code']) ? (string) $t['error_code'] : null;
            $safe = OpenpayErrorMap::message($errorCode ?? 3001);
        }

        return new ChargeResult(
            status: $status,
            transactionId: isset($t['id']) ? (string) $t['id'] : null,
            orderId: isset($t['order_id']) ? (string) $t['order_id'] : null,
            authorization: isset($t['authorization']) && $t['authorization'] !== '' ? (string) $t['authorization'] : null,
            redirectUrl: $status === ChargeStatus::Pending && isset($paymentMethod['url']) ? (string) $paymentMethod['url'] : null,
            cardBrand: isset($card['brand']) ? mb_substr((string) $card['brand'], 0, 24) : null,
            last4: strlen($digits) >= 4 ? substr($digits, -4) : null,
            errorCode: $errorCode,
            safeMessage: $safe,
            amount: self::money($t['amount'] ?? null),
            fee: self::money($fee['amount'] ?? null),
            refundedAmount: self::money($refund['amount'] ?? null),
            refundId: isset($refund['id']) ? (string) $refund['id'] : null,
            httpStatus: $httpStatus,
        );
    }

    private function parseError(Response $response, bool $forCharge): ChargeResult
    {
        $code = $response->json('error_code');
        $code = $code !== null ? (string) $code : null;
        $status = $response->status();

        if ((int) $code === 1006) {
            return new ChargeResult(status: ChargeStatus::DuplicateOrder, errorCode: $code, httpStatus: $status);
        }

        if (! $forCharge && ((int) $code === 1005 || $status === 404)) {
            return ChargeResult::notFound();
        }

        if (OpenpayErrorMap::isUncertain($code, $status)) {
            return ChargeResult::unknown($status, $code);
        }

        return new ChargeResult(
            status: ChargeStatus::Declined,
            errorCode: $code ?? (string) $status,
            safeMessage: OpenpayErrorMap::message($code),
            httpStatus: $status,
        );
    }

    /** Montos del proveedor (número JSON) a Money con 2 decimales. */
    private static function money(mixed $value): ?Money
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return Money::of(is_string($value) ? $value : number_format((float) $value, 2, '.', ''));
    }

    private function log(string $op, ?string $orderId, ?int $http, ChargeStatus $status, ?string $errorCode): void
    {
        Log::info('openpay.'.$op, array_filter([
            'order_id' => $orderId,
            'http' => $http,
            'status' => $status->value,
            'error_code' => $errorCode,
            'env' => $this->config->isProduction() ? 'production' : 'sandbox',
        ], fn ($v) => $v !== null));
    }
}
