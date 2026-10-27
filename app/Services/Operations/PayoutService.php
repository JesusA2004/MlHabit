<?php

namespace App\Services\Operations;

use App\Enums\FabricationStatus;
use App\Enums\InstallationStatus;
use App\Enums\PayoutStatus;
use App\Enums\Permission;
use App\Models\FabricationOrder;
use App\Models\InstallationOrder;
use App\Models\SupplierPayout;
use App\Models\User;
use App\Notifications\Operations\PayoutAuthorized;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pagos a proveedores con separación de funciones (validada aquí, además
 * de la Policy):
 *  - autoriza: payouts.authorize, y NO es quien lo solicitó ni el
 *    instalador líder de la orden;
 *  - paga: payouts.pay, solo pagos autorizados y con referencia.
 * Autorizar dos veces es idempotente.
 */
class PayoutService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly OperationsNotifier $notifier,
    ) {}

    public function requestForInstallation(InstallationOrder $order, User $actor): SupplierPayout
    {
        return DB::transaction(function () use ($order, $actor) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InstallationStatus::Approved) {
                throw ValidationException::withMessages(['payout' => 'Solo se solicita el pago de instalaciones aprobadas por calidad.']);
            }

            if ($existing = SupplierPayout::query()->where('installation_order_id', $locked->id)->first()) {
                return $existing;
            }

            if (! Money::of($locked->total)->isPositive()) {
                throw ValidationException::withMessages(['payout' => 'La orden no tiene un total a pagar.']);
            }

            $payout = SupplierPayout::query()->create([
                'folio' => Folio::next('S'),
                'supplier_id' => $locked->supplier_id,
                'installation_order_id' => $locked->id,
                'amount' => Money::of($locked->total)->amount,
                'status' => PayoutStatus::PendingAuthorization,
                'requested_by' => $actor->id,
            ]);

            $this->logger->log('pago_proveedor.solicitado', $payout, ['after' => $payout->only(['folio', 'amount', 'installation_order_id'])]);

            return $payout;
        });
    }

    public function requestForFabrication(FabricationOrder $order, User $actor): SupplierPayout
    {
        return DB::transaction(function () use ($order, $actor) {
            /** @var FabricationOrder $locked */
            $locked = FabricationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [FabricationStatus::Draft, FabricationStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['payout' => 'La orden debe estar emitida para solicitar su pago.']);
            }

            if ($existing = SupplierPayout::query()->where('fabrication_order_id', $locked->id)->first()) {
                return $existing;
            }

            if ($locked->cost === null || ! Money::of($locked->cost)->isPositive()) {
                throw ValidationException::withMessages(['payout' => 'Captura el costo de la orden antes de solicitar el pago.']);
            }

            $payout = SupplierPayout::query()->create([
                'folio' => Folio::next('S'),
                'supplier_id' => $locked->supplier_id,
                'fabrication_order_id' => $locked->id,
                'amount' => Money::of($locked->cost)->amount,
                'status' => PayoutStatus::PendingAuthorization,
                'requested_by' => $actor->id,
            ]);

            $this->logger->log('pago_proveedor.solicitado', $payout, ['after' => $payout->only(['folio', 'amount', 'fabrication_order_id'])]);

            return $payout;
        });
    }

    /** ¿Puede este usuario autorizar este pago? (permiso + separación de funciones) */
    public static function canAuthorize(User $user, SupplierPayout $payout): bool
    {
        if (! $user->hasPermission(Permission::PayoutsAuthorize)) {
            return false;
        }

        if ($payout->requested_by !== null && $payout->requested_by === $user->id) {
            return false;
        }

        $order = $payout->installationOrder;

        return ! ($order !== null && $order->lead_installer_id === $user->id);
    }

    public function authorize(SupplierPayout $payout, User $actor): SupplierPayout
    {
        $justAuthorized = false;
        $payout = DB::transaction(function () use ($payout, $actor, &$justAuthorized) {
            /** @var SupplierPayout $locked */
            $locked = SupplierPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! self::canAuthorize($actor, $locked)) {
                $this->logger->log('pago_proveedor.autorizacion_denegada', $locked, ['by' => $actor->id], 'denied');
                throw new AuthorizationException('No puedes autorizar este pago: quien solicita o ejecuta un trabajo no lo autoriza.');
            }

            if (in_array($locked->status, [PayoutStatus::Authorized, PayoutStatus::Paid], true)) {
                return $locked;
            }

            if ($locked->status !== PayoutStatus::PendingAuthorization) {
                throw ValidationException::withMessages(['status' => 'Este pago fue rechazado; vuelve a solicitarlo.']);
            }

            $locked->update(['status' => PayoutStatus::Authorized, 'authorized_by' => $actor->id, 'authorized_at' => now()]);
            $locked->installationOrder?->update(['status' => InstallationStatus::PaymentAuthorized]);

            $this->logger->log('pago_proveedor.autorizado', $locked, ['before' => ['status' => 'pending_authorization'], 'after' => ['status' => 'authorized'], 'amount' => $locked->amount]);
            $justAuthorized = true;

            return $locked;
        });

        if ($justAuthorized) {
            $this->notifier->send(
                [$payout->requester, ...$this->notifier->staffWith(Permission::PayoutsPay, $actor)],
                new PayoutAuthorized($payout),
            );
        }

        return $payout;
    }

    public function pay(SupplierPayout $payout, User $actor, string $reference): SupplierPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reference) {
            /** @var SupplierPayout $locked */
            $locked = SupplierPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! $actor->hasPermission(Permission::PayoutsPay) || $locked->installationOrder?->lead_installer_id === $actor->id) {
                throw new AuthorizationException('No puedes registrar este pago.');
            }

            if ($locked->status === PayoutStatus::Paid) {
                return $locked;
            }

            if ($locked->status !== PayoutStatus::Authorized) {
                throw ValidationException::withMessages(['status' => 'Solo se pagan solicitudes autorizadas.']);
            }

            $locked->update([
                'status' => PayoutStatus::Paid,
                'paid_by' => $actor->id,
                'paid_at' => now(),
                'payment_reference' => trim($reference),
            ]);
            $locked->installationOrder?->update(['status' => InstallationStatus::Paid]);

            $this->logger->log('pago_proveedor.pagado', $locked, ['after' => ['status' => 'paid', 'reference' => $reference, 'amount' => $locked->amount]]);

            return $locked;
        });
    }

    public function reject(SupplierPayout $payout, User $actor, string $reason): SupplierPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reason) {
            /** @var SupplierPayout $locked */
            $locked = SupplierPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! self::canAuthorize($actor, $locked)) {
                throw new AuthorizationException('No puedes dictaminar este pago.');
            }

            if (! in_array($locked->status, [PayoutStatus::PendingAuthorization, PayoutStatus::Authorized], true)) {
                throw ValidationException::withMessages(['status' => 'Este pago ya no se puede rechazar.']);
            }

            $before = $locked->status;
            $locked->update(['status' => PayoutStatus::Rejected, 'rejection_reason' => $reason, 'authorized_by' => null, 'authorized_at' => null]);

            if ($locked->installationOrder?->status === InstallationStatus::PaymentAuthorized) {
                $locked->installationOrder->update(['status' => InstallationStatus::Approved]);
            }

            $this->logger->log('pago_proveedor.rechazado', $locked, ['before' => ['status' => $before->value], 'reason' => $reason]);

            return $locked;
        });
    }

    /** Vuelve a solicitar un pago rechazado (con el monto vigente de la orden). */
    public function resubmit(SupplierPayout $payout, User $actor): SupplierPayout
    {
        return DB::transaction(function () use ($payout, $actor) {
            /** @var SupplierPayout $locked */
            $locked = SupplierPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PayoutStatus::Rejected) {
                throw ValidationException::withMessages(['status' => 'Solo se vuelve a solicitar un pago rechazado.']);
            }

            $amount = $locked->installationOrder->total ?? $locked->fabricationOrder->cost ?? $locked->amount;

            $locked->update([
                'status' => PayoutStatus::PendingAuthorization,
                'amount' => Money::of($amount)->amount,
                'requested_by' => $actor->id,
                'rejection_reason' => null,
            ]);
            $this->logger->log('pago_proveedor.resolicitado', $locked, ['amount' => $locked->amount]);

            return $locked;
        });
    }
}
