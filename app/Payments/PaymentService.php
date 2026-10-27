<?php

namespace App\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\RefundStatus;
use App\Events\PaymentConfirmed;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Refund;
use App\Models\User;
use App\Payments\Openpay\OpenpayConfig;
use App\Payments\Openpay\OpenpayErrorMap;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Única puerta para cambiar el estado de un pago (contrato en docs/EQUIPO.md).
 *
 * Reglas de oro:
 *  - Nunca se confía en el frontend como prueba de pago: solo en la
 *    respuesta del API, en una consulta (reconcile) o en un registro manual
 *    autorizado. Los webhooks solo disparan una re-consulta.
 *  - Un clic = una clave de intento (payment_attempts.idempotency_key
 *    UNIQUE). Repetir la clave nunca genera otro cargo.
 *  - Si un cargo queda sin respuesta concluyente (timeout/5xx), el pago se
 *    queda en `processing` y se concilia por order_id ANTES de permitir otro
 *    intento: así un reintento/recarga no cobra dos veces.
 *  - PaymentConfirmed/PaymentFailed/PaymentRefunded se despachan después del
 *    COMMIT (DB::afterCommit) y una sola vez por transición.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OpenpayConfig $config,
        private readonly ActivityLogger $audit,
    ) {}

    // ------------------------------------------------------------------ Intención

    /**
     * Crea (o devuelve, si la clave ya existe) la intención de cobro.
     *
     * @param  array{site_visit_id?: int|null, project_id?: int|null, contract_id?: int|null, return_url?: string|null}  $links
     */
    public function createIntent(Customer $customer, PaymentConcept $concept, Money $amount, string $description, array $links, string $idempotencyKey): Payment
    {
        if (! $amount->isPositive()) {
            throw new PaymentException('El monto a cobrar debe ser mayor a cero.', PaymentException::INVALID_AMOUNT);
        }

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $this->assertSameIntent($existing, $customer, $amount);
        }

        try {
            return DB::transaction(function () use ($customer, $concept, $amount, $description, $links, $idempotencyKey) {
                $payment = Payment::query()->create([
                    'folio' => Folio::next('G'),
                    'idempotency_key' => $idempotencyKey,
                    'customer_id' => $customer->id,
                    'site_visit_id' => $links['site_visit_id'] ?? null,
                    'project_id' => $links['project_id'] ?? null,
                    'contract_id' => $links['contract_id'] ?? null,
                    'concept' => $concept,
                    'description' => mb_substr($description, 0, 250),
                    'method' => PaymentMethod::Card,
                    'provider' => PaymentProvider::Openpay,
                    'amount' => $amount->amount,
                    'currency' => 'MXN',
                    'status' => PaymentStatus::Pending,
                    'refunded_amount' => '0.00',
                    'metadata' => isset($links['return_url']) ? ['return_url' => $links['return_url']] : null,
                ]);

                $this->event($payment, 'system', 'created', null, PaymentStatus::Pending, [
                    'amount' => $amount->amount,
                    'concept' => $concept->value,
                ]);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->assertSameIntent(Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(), $customer, $amount);
        }
    }

    /** URL a la que vuelve el cliente tras 3D Secure (si el módulo no pasa una propia). */
    public function setReturnUrl(Payment $payment, string $url): void
    {
        $payment->forceFill(['metadata' => array_merge($payment->metadata ?? [], ['return_url' => $url])])->save();
    }

    // ------------------------------------------------------------------ Cobro con tarjeta

    /**
     * Cobra con un token de Openpay.js. `attempt_key` (UUID por envío del
     * formulario) se lee del Request. Devuelve el pago actualizado; si quedó
     * en `processing` con `redirect_url`, el cliente debe ir a 3D Secure.
     *
     * @throws PaymentException
     */
    public function chargeCard(Payment $payment, string $tokenId, string $deviceSessionId, Request $request, ?string $returnUrl = null): Payment
    {
        $attemptKey = (string) ($request->input('attempt_key') ?: Str::uuid());
        $ip = (string) $request->ip();

        if (! $this->gateway->isConfigured()) {
            throw new PaymentException('Los pagos con tarjeta no están disponibles en este momento. No se realizó ningún cargo.', PaymentException::NOT_CONFIGURED);
        }

        // Doble clic / reenvío del mismo formulario: nunca otro cargo.
        if (PaymentAttempt::query()->where('idempotency_key', $attemptKey)->exists()) {
            return $payment->fresh() ?? $payment;
        }

        // Un intento anterior sin respuesta concluyente se concilia ANTES.
        if ($payment->fresh()?->status === PaymentStatus::Processing) {
            $payment = $this->reconcile($payment);
        }

        $mode = $this->config->threeDsMode();
        $use3ds = $mode === 'always';
        $returnUrl ??= $this->returnUrlFor($payment);

        $attempt = $this->openAttempt($payment, $attemptKey, $ip);
        if ($attempt === null) {
            return $payment->fresh() ?? $payment;
        }

        $result = $this->sendCharge($payment, $attempt, $tokenId, $deviceSessionId, $ip, $use3ds, $returnUrl);

        // Autenticación selectiva: rechazo antifraude (3005) → mismo token con 3D Secure.
        if ($mode === 'auto' && $result->status === ChargeStatus::Declined && OpenpayErrorMap::isFraudDecline($result->errorCode)) {
            $this->updateAttempt($attempt, PaymentAttemptStatus::Declined, $result);
            $this->event($payment, 'api', 'selective_3ds', null, null, ['attempt' => $attempt->number]);

            $attempt = $this->openAttempt($payment, mb_substr($attemptKey, 0, 59).':3ds', $ip, allowProcessing: true);
            if ($attempt === null) {
                return $payment->fresh() ?? $payment;
            }

            $result = $this->sendCharge($payment, $attempt, $tokenId, $deviceSessionId, $ip, true, $returnUrl);
        }

        if ($result->status === ChargeStatus::DuplicateOrder) {
            // El order_id ya existe en Openpay: consultamos y convergemos, nunca re-cobramos.
            $this->updateAttempt($attempt, PaymentAttemptStatus::Unknown, $result);
            $found = $this->gateway->findChargeByOrderId($payment->attemptOrderId($attempt->number), $ip);
            if (! in_array($found->status, [ChargeStatus::NotFound, ChargeStatus::Unknown], true)) {
                $this->converge($payment, $attempt, $found, 'api');
            }

            return $payment->fresh() ?? $payment;
        }

        $this->converge($payment, $attempt, $result, 'api');

        return $payment->fresh() ?? $payment;
    }

    /**
     * Abre un intento bajo bloqueo de la fila del pago.
     *
     * @throws PaymentException
     */
    private function openAttempt(Payment $payment, string $key, string $ip, bool $allowProcessing = false): ?PaymentAttempt
    {
        return DB::transaction(function () use ($payment, $key, $ip, $allowProcessing) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (PaymentAttempt::query()->where('idempotency_key', $key)->exists()) {
                return null;
            }

            if ($locked->status->isSettled()) {
                throw new PaymentException('Este pago ya está cubierto. No se realizó otro cargo.', PaymentException::ALREADY_PAID);
            }

            if ($locked->status === PaymentStatus::Cancelled) {
                throw new PaymentException('Esta solicitud de pago ya no está vigente. No se realizó ningún cargo.', PaymentException::NOT_PAYABLE);
            }

            if ($locked->status === PaymentStatus::Processing && ! $allowProcessing) {
                throw new PaymentException('Estamos confirmando un intento anterior con tu banco. Espera unos segundos antes de volver a intentarlo.', PaymentException::IN_PROGRESS);
            }

            if ($locked->provider !== PaymentProvider::Openpay) {
                throw new PaymentException('Este pago no se cobra con tarjeta.', PaymentException::NOT_PAYABLE);
            }

            $number = ((int) PaymentAttempt::query()->where('payment_id', $locked->id)->max('number')) + 1;

            $attempt = PaymentAttempt::query()->create([
                'payment_id' => $locked->id,
                'number' => $number,
                'idempotency_key' => $key,
                'status' => PaymentAttemptStatus::Sending,
                'ip_address' => $ip,
            ]);

            if ($locked->status !== PaymentStatus::Processing) {
                $from = $locked->status;
                $locked->forceFill(['status' => PaymentStatus::Processing, 'error_code' => null, 'error_message' => null])->save();
                $this->event($locked, 'api', 'attempt_started', $from, PaymentStatus::Processing, ['attempt' => $number]);
            }

            return $attempt;
        });
    }

    private function sendCharge(Payment $payment, PaymentAttempt $attempt, string $tokenId, string $deviceSessionId, string $ip, bool $use3ds, ?string $returnUrl): ChargeResult
    {
        return $this->gateway->createCardCharge(new ChargeRequest(
            orderId: $payment->attemptOrderId($attempt->number),
            tokenId: $tokenId,
            deviceSessionId: $deviceSessionId,
            amount: $payment->amountMoney(),
            currency: $payment->currency,
            description: $payment->description,
            customer: $this->customerPayload($payment),
            customerIp: $ip,
            use3ds: $use3ds,
            redirectUrl: $use3ds ? $returnUrl : null,
        ));
    }

    // ------------------------------------------------------------------ Conciliación

    /**
     * Consulta Openpay y converge el estado local. Seguro de llamar muchas
     * veces (retorno 3DS, webhook, comando programado, botón del panel).
     */
    public function reconcile(Payment $payment, ?string $transactionId = null): Payment
    {
        $payment = $payment->fresh() ?? $payment;

        if ($payment->provider !== PaymentProvider::Openpay || ! $this->gateway->isConfigured()) {
            return $payment;
        }

        if ($payment->status->isSettled()) {
            $txId = $transactionId ?? $payment->provider_transaction_id;
            if ($txId !== null) {
                $result = $this->gateway->getCharge($txId, $this->customerIp($payment));
                $attempt = $txId !== $payment->provider_transaction_id
                    ? PaymentAttempt::query()->where('payment_id', $payment->id)->where('provider_transaction_id', $txId)->first()
                    : null;
                if (! in_array($result->status, [ChargeStatus::Unknown, ChargeStatus::NotFound], true)) {
                    $this->converge($payment, $attempt, $result, 'reconcile');
                }
            }
        } else {
            $attempts = PaymentAttempt::query()->where('payment_id', $payment->id)->orderByDesc('number')->get()
                ->filter(fn (PaymentAttempt $a) => $a->status->isOpen() || ($transactionId !== null && $a->provider_transaction_id === $transactionId));

            foreach ($attempts as $attempt) {
                $result = $attempt->provider_transaction_id
                    ? $this->gateway->getCharge($attempt->provider_transaction_id, $attempt->ip_address)
                    : $this->gateway->findChargeByOrderId($payment->attemptOrderId($attempt->number), $attempt->ip_address);

                if ($result->status === ChargeStatus::Unknown) {
                    continue;
                }

                $age = $attempt->created_at?->diffInSeconds(now()) ?? 0;

                if ($result->status === ChargeStatus::NotFound) {
                    if ($age >= $this->config->unknownGraceSeconds()) {
                        $this->expireAttempt($payment, $attempt, 'no_registrado');
                    }

                    continue;
                }

                if ($result->status === ChargeStatus::Pending && $age >= $this->config->pending3dsMinutes() * 60) {
                    $this->expireAttempt($payment, $attempt, '3ds_abandonado');

                    continue;
                }

                $this->converge($payment, $attempt, $result, 'reconcile');

                if ($payment->fresh()?->status->isSettled()) {
                    break;
                }
            }

            $payment = $payment->fresh() ?? $payment;
            $stillOpen = PaymentAttempt::query()->where('payment_id', $payment->id)->get()->contains(fn (PaymentAttempt $a) => $a->status->isOpen());

            if ($payment->status === PaymentStatus::Processing && ! $stillOpen) {
                $last = PaymentAttempt::query()->where('payment_id', $payment->id)->orderByDesc('number')->first();
                $this->markFailed(
                    $payment,
                    $last->error_code ?? 'expired',
                    $last->error_message ?? 'El cobro no se completó y no se realizó ningún cargo. Puedes intentarlo de nuevo.',
                    'reconcile',
                );
            }
        }

        Payment::query()->whereKey($payment->id)->update(['reconciled_at' => now()]);

        return $payment->fresh() ?? $payment;
    }

    /** Aplica un resultado del proveedor al pago (y al intento, si se conoce). */
    public function converge(Payment $payment, ?PaymentAttempt $attempt, ChargeResult $result, string $source): void
    {
        $payment = $payment->fresh() ?? $payment;

        switch ($result->status) {
            case ChargeStatus::Completed:
                if ($attempt) {
                    $this->updateAttempt($attempt, PaymentAttemptStatus::Approved, $result);
                }
                if ($payment->status === PaymentStatus::Disputed && $result->transactionId === $payment->provider_transaction_id) {
                    $this->resolveDispute($payment, $source);
                } else {
                    $this->markPaid($payment, $result, $source);
                }
                break;

            case ChargeStatus::Pending:
                if ($attempt) {
                    $this->updateAttempt($attempt, $result->redirectUrl ? PaymentAttemptStatus::RequiresAction : PaymentAttemptStatus::Sending, $result);
                }
                if (! $payment->status->isSettled()) {
                    $this->setProcessing($payment, $result->redirectUrl, $source);
                }
                break;

            case ChargeStatus::Failed:
            case ChargeStatus::Cancelled:
            case ChargeStatus::Declined:
                if ($attempt) {
                    $this->updateAttempt($attempt, PaymentAttemptStatus::Declined, $result);
                }
                if (! $payment->status->isSettled()) {
                    $otherOpen = PaymentAttempt::query()->where('payment_id', $payment->id)
                        ->when($attempt, fn ($q) => $q->whereKeyNot($attempt->id))
                        ->get()->contains(fn (PaymentAttempt $a) => $a->status->isOpen());
                    if (! $otherOpen) {
                        $this->markFailed($payment, $result->errorCode, $result->safeMessage ?? OpenpayErrorMap::message(null), $source);
                    }
                } elseif ($result->transactionId !== null && $result->transactionId === $payment->provider_transaction_id) {
                    $this->event($payment, $source, 'remote_state_mismatch', null, null, $result->toSafeArray());
                    $this->audit->log('pago.inconsistencia_remota', $payment, ['remoto' => $result->status->value], 'warning');
                }
                break;

            case ChargeStatus::Refunded:
                if ($attempt) {
                    $this->updateAttempt($attempt, PaymentAttemptStatus::Approved, $result);
                }
                if (! $payment->status->isSettled()) {
                    $this->markPaid($payment, $result, $source);
                }
                $this->syncRemoteRefund($payment, $result, $source);
                break;

            case ChargeStatus::ChargebackPending:
            case ChargeStatus::ChargebackAccepted:
            case ChargeStatus::ChargebackAdjustment:
                if ($attempt) {
                    $this->updateAttempt($attempt, PaymentAttemptStatus::Approved, $result);
                }
                if (! $payment->status->isSettled()) {
                    $this->markPaid($payment, $result, $source);
                }
                $this->markDisputed($payment, $result, $source);
                break;

            case ChargeStatus::DuplicateOrder:
            case ChargeStatus::NotFound:
            case ChargeStatus::Unknown:
                if ($attempt && $attempt->status === PaymentAttemptStatus::Sending) {
                    $this->updateAttempt($attempt, PaymentAttemptStatus::Unknown, $result);
                    $this->event($payment, $source, 'outcome_unknown', null, null, ['attempt' => $attempt->number] + $result->toSafeArray());
                }
                break;
        }
    }

    // ------------------------------------------------------------------ Transiciones

    /** Marca pagado (idempotente). Despacha PaymentConfirmed UNA vez, tras el commit. */
    public function markPaid(Payment $payment, ?ChargeResult $result, string $source, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $result, $source, $actor) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isSettled()) {
                if ($result?->transactionId !== null && $locked->provider_transaction_id !== null && $result->transactionId !== $locked->provider_transaction_id) {
                    $this->event($locked, $source, 'duplicate_charge_detected', null, null, $result->toSafeArray());
                    $this->audit->log('pago.cobro_duplicado', $locked, [
                        'folio' => $locked->folio,
                        'transaccion_extra' => $result->transactionId,
                        'accion' => 'Revisar y reembolsar el cargo duplicado desde el panel de Openpay o /admin/pagos.',
                    ], 'warning');
                }

                return $locked;
            }

            $from = $locked->status;
            $fee = $result?->fee;

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'provider_transaction_id' => $result->transactionId ?? $locked->provider_transaction_id,
                'provider_authorization' => $result->authorization ?? $locked->provider_authorization,
                'card_brand' => $result->cardBrand ?? $locked->card_brand,
                'card_last4' => $result->last4 ?? $locked->card_last4,
                'fee_amount' => $fee?->amount,
                'net_amount' => $fee ? $locked->amountMoney()->minus($fee)->amount : null,
                'error_code' => null,
                'error_message' => null,
                'redirect_url' => null,
            ])->save();

            $this->event($locked, $source, 'paid', $from, PaymentStatus::Paid, $result?->toSafeArray() ?? [], $actor);
            $this->audit->log('pago.confirmado', $locked, [
                'folio' => $locked->folio,
                'monto' => $locked->amount,
                'concepto' => $locked->concept->value,
                'origen' => $source,
            ]);

            DB::afterCommit(function () use ($locked) {
                PaymentConfirmed::dispatch($locked->fresh() ?? $locked);
            });

            return $locked;
        });
    }

    public function markFailed(Payment $payment, ?string $errorCode, string $safeMessage, string $source): Payment
    {
        return DB::transaction(function () use ($payment, $errorCode, $safeMessage, $source) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                return $locked;
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
                'error_code' => $errorCode !== null ? mb_substr($errorCode, 0, 16) : null,
                'error_message' => mb_substr($safeMessage, 0, 300),
                'redirect_url' => null,
            ])->save();

            $this->event($locked, $source, 'failed', $from, PaymentStatus::Failed, ['error_code' => $errorCode]);

            DB::afterCommit(function () use ($locked) {
                PaymentFailed::dispatch($locked->fresh() ?? $locked);
            });

            return $locked;
        });
    }

    /** Cancela una intención sin cobro (apartado vencido, visita cancelada). False si hay un cobro en curso o ya pagado. */
    public function cancel(Payment $payment, string $reason, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($payment, $reason, $actor) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Cancelled) {
                return true;
            }

            if (! in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                return false;
            }

            $from = $locked->status;
            $locked->forceFill(['status' => PaymentStatus::Cancelled, 'redirect_url' => null])->save();
            $this->event($locked, 'system', 'cancelled', $from, PaymentStatus::Cancelled, ['motivo' => $reason], $actor);

            return true;
        });
    }

    private function setProcessing(Payment $payment, ?string $redirectUrl, string $source): void
    {
        DB::transaction(function () use ($payment, $redirectUrl, $source) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isSettled()) {
                return;
            }

            $from = $locked->status;
            $changed = $from !== PaymentStatus::Processing || $locked->redirect_url !== $redirectUrl;

            $locked->forceFill(['status' => PaymentStatus::Processing, 'redirect_url' => $redirectUrl])->save();

            if ($changed) {
                $this->event($locked, $source, $redirectUrl ? 'requires_3ds' : 'pending', $from, PaymentStatus::Processing);
            }
        });
    }

    private function markDisputed(Payment $payment, ChargeResult $result, string $source): void
    {
        DB::transaction(function () use ($payment, $result, $source) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                if ($locked->status === PaymentStatus::Disputed) {
                    $this->event($locked, $source, 'chargeback_update', null, null, ['remoto' => $result->status->value]);
                }

                return;
            }

            $from = $locked->status;
            $locked->forceFill(['status' => PaymentStatus::Disputed])->save();
            $this->event($locked, $source, 'chargeback', $from, PaymentStatus::Disputed, ['remoto' => $result->status->value]);
            $this->audit->log('pago.contracargo', $locked, ['folio' => $locked->folio, 'remoto' => $result->status->value], 'warning');
        });
    }

    /** Contracargo ganado por el comercio: el cargo vuelve a `completed` en Openpay. */
    private function resolveDispute(Payment $payment, string $source): void
    {
        DB::transaction(function () use ($payment, $source) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Disputed) {
                return;
            }

            $to = $locked->refundedMoney()->isPositive() ? PaymentStatus::PartiallyRefunded : PaymentStatus::Paid;
            $locked->forceFill(['status' => $to])->save();
            $this->event($locked, $source, 'chargeback_won', PaymentStatus::Disputed, $to);
            $this->audit->log('pago.contracargo_ganado', $locked, ['folio' => $locked->folio]);
        });
    }

    // ------------------------------------------------------------------ Reembolsos

    /**
     * Reembolso total o parcial (requiere payments.refund). Idempotente por
     * clave: repetir la misma clave devuelve el mismo reembolso.
     *
     * @throws AuthorizationException|PaymentException
     */
    public function refund(Payment $payment, Money $amount, string $reason, string $idempotencyKey, User $actor): Refund
    {
        if (! $actor->hasPermission(Permission::PaymentsRefund)) {
            throw new AuthorizationException('No tienes permiso para reembolsar pagos.');
        }

        $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (! $amount->isPositive()) {
            throw new PaymentException('El monto a reembolsar debe ser mayor a cero.', PaymentException::INVALID_AMOUNT);
        }

        $created = false;

        /** @var Refund $refund */
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $idempotencyKey, $actor, &$created) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $again = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($again !== null) {
                return $again;
            }

            if (! $locked->status->isRefundable()) {
                throw new PaymentException('Este pago no se puede reembolsar en su estado actual.', PaymentException::NOT_PAYABLE);
            }

            $pending = Money::of((string) (Refund::query()->where('payment_id', $locked->id)->where('status', RefundStatus::Pending->value)->sum('amount') ?: '0'));
            $available = $locked->refundableMoney()->minus($pending);

            if ($amount->compare($available) > 0) {
                throw new PaymentException('El monto excede lo disponible para reembolso ('.$available->format().').', PaymentException::INVALID_AMOUNT);
            }

            $created = true;

            $refund = Refund::query()->create([
                'payment_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount->amount,
                'reason' => mb_substr($reason, 0, 300),
                'status' => RefundStatus::Pending,
                'requested_by' => $actor->id,
            ]);

            $this->event($locked, 'manual', 'refund_requested', null, null, ['monto' => $amount->amount, 'reembolso' => $refund->uuid], $actor);

            return $refund;
        });

        if (! $created) {
            return $refund;
        }

        $payment = $payment->fresh() ?? $payment;

        $result = $payment->provider === PaymentProvider::Openpay && $payment->provider_transaction_id !== null
            ? $this->gateway->refund($payment->provider_transaction_id, $amount, $reason, $this->customerIp($payment))
            // Pago manual: la devolución se hace fuera de la pasarela y aquí solo se registra.
            : new RefundResult(RefundResult::COMPLETED);

        return $this->applyRefundResult($payment, $refund, $result, 'api', $actor);
    }

    private function applyRefundResult(Payment $payment, Refund $refund, RefundResult $result, string $source, ?User $actor): Refund
    {
        if ($result->outcome === RefundResult::FAILED) {
            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'error_code' => $result->errorCode !== null ? mb_substr($result->errorCode, 0, 16) : null,
                'error_message' => $result->safeMessage !== null ? mb_substr($result->safeMessage, 0, 300) : null,
                'processed_at' => now(),
            ])->save();
            $this->event($payment, $source, 'refund_failed', null, null, ['reembolso' => $refund->uuid, 'error_code' => $result->errorCode], $actor);
            $this->audit->log('pago.reembolso_rechazado', $payment, ['folio' => $payment->folio, 'monto' => $refund->amount], 'error');

            return $refund;
        }

        if ($result->outcome === RefundResult::UNKNOWN) {
            $refund->forceFill(['error_message' => $result->safeMessage !== null ? mb_substr($result->safeMessage, 0, 300) : null])->save();
            $this->event($payment, $source, 'refund_unknown', null, null, ['reembolso' => $refund->uuid], $actor);

            return $refund;
        }

        $this->completeRefund($payment, $refund, $result->refundId, $source, $actor);

        return $refund->fresh() ?? $refund;
    }

    private function completeRefund(Payment $payment, Refund $refund, ?string $providerRefundId, string $source, ?User $actor): void
    {
        DB::transaction(function () use ($payment, $refund, $providerRefundId, $source, $actor) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            /** @var Refund $lockedRefund */
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($lockedRefund->status === RefundStatus::Completed) {
                return;
            }

            $lockedRefund->forceFill([
                'status' => RefundStatus::Completed,
                'provider_refund_id' => $providerRefundId,
                'processed_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $refunded = $locked->refundedMoney()->plus($lockedRefund->amountMoney())->min($locked->amountMoney());
            $from = $locked->status;
            $to = $locked->status === PaymentStatus::Disputed
                ? PaymentStatus::Disputed
                : ($refunded->compare($locked->amountMoney()) >= 0 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded);

            $locked->forceFill(['refunded_amount' => $refunded->amount, 'status' => $to])->save();

            $this->event($locked, $source, 'refunded', $from, $to, ['monto' => $lockedRefund->amount, 'reembolso' => $lockedRefund->uuid], $actor);
            $this->audit->log('pago.reembolsado', $locked, [
                'folio' => $locked->folio,
                'monto' => $lockedRefund->amount,
                'motivo' => $lockedRefund->reason,
                'reembolsado_total' => $refunded->amount,
            ]);

            DB::afterCommit(function () use ($locked, $lockedRefund) {
                PaymentRefunded::dispatch($locked->fresh() ?? $locked, $lockedRefund->fresh() ?? $lockedRefund);
            });
        });
    }

    /** Openpay reporta el cargo como reembolsado (desde nuestro panel o desde el suyo). */
    private function syncRemoteRefund(Payment $payment, ChargeResult $result, string $source): void
    {
        $payment = $payment->fresh() ?? $payment;
        $remaining = $payment->refundableMoney();

        if (! $remaining->isPositive()) {
            return;
        }

        // Primero se confirman los reembolsos que quedaron "por confirmar".
        foreach (Refund::query()->where('payment_id', $payment->id)->where('status', RefundStatus::Pending->value)->orderBy('id')->get() as $pending) {
            $this->completeRefund($payment, $pending, $result->refundId, $source, null);
        }

        $payment = $payment->fresh() ?? $payment;
        $remaining = $payment->refundableMoney();

        if (! $remaining->isPositive()) {
            return;
        }

        $key = 'remote:'.($result->refundId ?? $result->transactionId ?? $payment->order_id);

        try {
            $refund = Refund::query()->firstOrCreate(['idempotency_key' => mb_substr($key, 0, 64)], [
                'payment_id' => $payment->id,
                'amount' => $remaining->amount,
                'reason' => 'Reembolso registrado en Openpay (fuera del panel de ML Habitat).',
                'status' => RefundStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $this->completeRefund($payment, $refund, $result->refundId, $source, null);
    }

    // ------------------------------------------------------------------ Pago manual

    /**
     * Registra un pago por transferencia/efectivo con su referencia (requiere
     * payments.record). Acepta un pago existente o los datos para crearlo:
     * {customer, concept, amount, description, links, idempotency_key}.
     *
     * @param  Payment|array{customer: Customer, concept: PaymentConcept, amount: Money, description: string, links?: array<string, mixed>, idempotency_key: string}  $target
     *
     * @throws AuthorizationException|PaymentException
     */
    public function recordManual(Payment|array $target, PaymentMethod|string $method, string $reference, User $actor): Payment
    {
        if (! $actor->hasPermission(Permission::PaymentsRecord)) {
            throw new AuthorizationException('No tienes permiso para registrar pagos manuales.');
        }

        $method = $method instanceof PaymentMethod ? $method : PaymentMethod::from($method);

        if ($method === PaymentMethod::Card) {
            throw new PaymentException('Los pagos con tarjeta solo se registran mediante Openpay.', PaymentException::NOT_PAYABLE);
        }

        $payment = $target instanceof Payment
            ? $target
            : $this->createIntent($target['customer'], $target['concept'], $target['amount'], $target['description'], $target['links'] ?? [], $target['idempotency_key']);

        return DB::transaction(function () use ($payment, $method, $reference, $actor) {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status->isSettled()) {
                return $locked;
            }

            if ($locked->status === PaymentStatus::Processing) {
                throw new PaymentException('Hay un cobro con tarjeta en curso para este pago. Concílialo antes de registrar un pago manual.', PaymentException::IN_PROGRESS);
            }

            $locked->forceFill([
                'method' => $method,
                'provider' => PaymentProvider::Manual,
                'reference' => mb_substr($reference, 0, 120),
                'recorded_by' => $actor->id,
            ])->save();

            $this->audit->log('pago.registro_manual', $locked, [
                'folio' => $locked->folio,
                'metodo' => $method->value,
                'referencia' => mb_substr($reference, 0, 120),
                'monto' => $locked->amount,
            ]);

            return $this->markPaid($locked, null, 'manual', $actor);
        });
    }

    // ------------------------------------------------------------------ Auxiliares

    private function expireAttempt(Payment $payment, PaymentAttempt $attempt, string $reason): void
    {
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Expired,
            'error_code' => $reason === '3ds_abandonado' ? '2010' : 'not_found',
            'error_message' => $reason === '3ds_abandonado'
                ? 'No se completó la autenticación con tu banco. No se realizó ningún cargo.'
                : 'El cobro no llegó a registrarse. No se realizó ningún cargo.',
        ])->save();

        $this->event($payment, 'reconcile', 'attempt_expired', null, null, ['attempt' => $attempt->number, 'motivo' => $reason]);
    }

    private function updateAttempt(PaymentAttempt $attempt, PaymentAttemptStatus $status, ChargeResult $result): void
    {
        $attempt->forceFill([
            'status' => $status,
            'provider_transaction_id' => $result->transactionId ?? $attempt->provider_transaction_id,
            'http_status' => $result->httpStatus ?? $attempt->http_status,
            'error_code' => $result->errorCode !== null ? mb_substr($result->errorCode, 0, 16) : ($status === PaymentAttemptStatus::Approved ? null : $attempt->error_code),
            'error_message' => $result->safeMessage !== null && $status !== PaymentAttemptStatus::Approved ? mb_substr($result->safeMessage, 0, 300) : ($status === PaymentAttemptStatus::Approved ? null : $attempt->error_message),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function event(Payment $payment, string $source, string $type, ?PaymentStatus $from, ?PaymentStatus $to, array $data = [], ?User $actor = null): void
    {
        PaymentEvent::query()->create([
            'payment_id' => $payment->id,
            'source' => $source,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'data' => $data !== [] ? ActivityLogger::sanitize($data) : null,
            'user_id' => $actor->id ?? Auth::id(),
            'created_at' => now(),
        ]);
    }

    /** @return array{name: string, last_name?: string|null, email: string, phone_number?: string|null} */
    private function customerPayload(Payment $payment): array
    {
        $customer = $payment->customer;
        $email = trim((string) $customer->email);

        if ($email === '') {
            throw new PaymentException('Necesitamos un correo electrónico para enviarte el comprobante.', PaymentException::NOT_PAYABLE);
        }

        $phone = preg_replace('/\D/', '', (string) $customer->phone) ?? '';

        return [
            'name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $email,
            'phone_number' => $phone !== '' ? substr($phone, -10) : null,
        ];
    }

    private function customerIp(Payment $payment): ?string
    {
        return PaymentAttempt::query()->where('payment_id', $payment->id)->whereNotNull('ip_address')->orderByDesc('number')->value('ip_address');
    }

    private function returnUrlFor(Payment $payment): string
    {
        $url = $payment->metadata['return_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : route('pagos.retorno', $payment);
    }

    private function assertSameIntent(Payment $payment, Customer $customer, Money $amount): Payment
    {
        if ($payment->customer_id !== $customer->id || ! $payment->amountMoney()->equals($amount)) {
            throw new PaymentException('La clave de idempotencia ya se usó para otro cobro.', PaymentException::IDEMPOTENCY_MISMATCH);
        }

        return $payment;
    }
}
