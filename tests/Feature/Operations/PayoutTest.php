<?php

use App\Enums\FabricationStatus;
use App\Enums\InstallationStatus;
use App\Enums\PayoutStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\FabricationOrder;
use App\Models\SupplierPayout;
use App\Notifications\Operations\PayoutAuthorized;
use Database\Factories\OperationsFixtures;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Operations\OpsSetup;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
    $this->lead = OpsSetup::installer();
    $this->ops = OpsSetup::staff(UserRole::Operations);
    $this->finance = OpsSetup::staff(UserRole::Finance);
    $this->order = OpsSetup::forceStatus(
        OpsSetup::order(project: OperationsFixtures::project(ProjectStatus::ReadyToInstall), lead: $this->lead),
        InstallationStatus::Approved,
        ProjectStatus::Completed,
    );
});

function requestPayout($test): SupplierPayout
{
    $test->actingAs($test->ops)->post(route('admin.instalaciones.pago', $test->order))->assertSessionHasNoErrors();

    return SupplierPayout::query()->sole();
}

it('requests a payout for an approved installation with the order total', function () {
    $payout = requestPayout($this);

    expect($payout->folio)->toStartWith('MLH-S-')
        ->and($payout->amount)->toBe($this->order->total)
        ->and($payout->status)->toBe(PayoutStatus::PendingAuthorization)
        ->and($payout->requested_by)->toBe($this->ops->id);

    // Solicitar dos veces no duplica.
    $this->actingAs($this->ops)->post(route('admin.instalaciones.pago', $this->order));
    expect(SupplierPayout::query()->count())->toBe(1);
});

it('does not request payouts for orders not approved by quality', function () {
    OpsSetup::forceStatus($this->order, InstallationStatus::QualityReview);

    $this->actingAs($this->ops)->post(route('admin.instalaciones.pago', $this->order))->assertSessionHasErrors('payout');
});

it('never lets the installer authorize their own payout', function () {
    $payout = requestPayout($this);

    // Aunque tuviera el rol de finanzas, el instalador líder no autoriza.
    $this->lead->update(['role' => UserRole::Finance]);
    $this->actingAs($this->lead->refresh())->post(route('admin.pagos-proveedores.autorizar', $payout))->assertForbidden();

    expect($payout->refresh()->status)->toBe(PayoutStatus::PendingAuthorization);
});

it('never lets the requester authorize', function () {
    $direction = OpsSetup::staff(UserRole::Direction); // tiene installations.manage y payouts.authorize
    $this->actingAs($direction)->post(route('admin.instalaciones.pago', $this->order))->assertSessionHasNoErrors();
    $payout = SupplierPayout::query()->sole();

    $this->actingAs($direction)->post(route('admin.pagos-proveedores.autorizar', $payout))->assertForbidden();
    expect($payout->refresh()->status)->toBe(PayoutStatus::PendingAuthorization);
});

it('authorizes once (idempotent) and notifies', function () {
    $payout = requestPayout($this);

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.autorizar', $payout))->assertSessionHasNoErrors();
    $authorizedAt = $payout->refresh()->authorized_at;

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.autorizar', $payout))->assertSessionHasNoErrors();

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Authorized)
        ->and($payout->authorized_by)->toBe($this->finance->id)
        ->and($payout->authorized_at->equalTo($authorizedAt))->toBeTrue()
        ->and($this->order->refresh()->status)->toBe(InstallationStatus::PaymentAuthorized)
        ->and(ActivityLog::query()->where('action', 'pago_proveedor.autorizado')->count())->toBe(1);

    Notification::assertSentTo($this->ops, PayoutAuthorized::class);
});

it('pays only authorized payouts and requires a reference', function () {
    $payout = requestPayout($this);

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.pagar', $payout), ['reference' => 'SPEI-123'])
        ->assertSessionHasErrors('status');
    expect($payout->refresh()->status)->toBe(PayoutStatus::PendingAuthorization);

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.autorizar', $payout));

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.pagar', $payout), [])->assertSessionHasErrors('reference');

    // Operaciones no tiene payouts.pay.
    $this->actingAs($this->ops)->post(route('admin.pagos-proveedores.pagar', $payout), ['reference' => 'SPEI-123'])->assertForbidden();

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.pagar', $payout), ['reference' => 'SPEI-123'])
        ->assertSessionHasNoErrors();

    expect($payout->refresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->payment_reference)->toBe('SPEI-123')
        ->and($payout->paid_by)->toBe($this->finance->id)
        ->and($this->order->refresh()->status)->toBe(InstallationStatus::Paid);
});

it('rejects with a reason and allows resubmission', function () {
    $payout = requestPayout($this);

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.rechazar', $payout), ['reason' => 'Falta factura'])
        ->assertSessionHasNoErrors();
    expect($payout->refresh()->status)->toBe(PayoutStatus::Rejected)->and($payout->rejection_reason)->toBe('Falta factura');

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.autorizar', $payout))->assertSessionHasErrors('status');

    $this->actingAs($this->ops)->post(route('admin.pagos-proveedores.reenviar', $payout))->assertSessionHasNoErrors();
    expect($payout->refresh()->status)->toBe(PayoutStatus::PendingAuthorization)->and($payout->rejection_reason)->toBeNull();
});

it('generates payouts for fabrication orders from their cost', function () {
    $fab = FabricationOrder::factory()->create(['status' => FabricationStatus::Issued, 'cost' => '18000.00']);

    $this->actingAs($this->ops)->post(route('admin.fabricacion.pago', $fab))->assertSessionHasNoErrors();

    $payout = SupplierPayout::query()->where('fabrication_order_id', $fab->id)->sole();
    expect($payout->amount)->toBe('18000.00');

    $this->actingAs($this->finance)->post(route('admin.pagos-proveedores.autorizar', $payout))->assertSessionHasNoErrors();
    expect($payout->refresh()->status)->toBe(PayoutStatus::Authorized);

    // El costo queda bloqueado una vez solicitado el pago.
    $this->actingAs($this->ops)->put(route('admin.fabricacion.update', $fab), ['supplier_id' => $fab->supplier_id, 'cost' => '1.00'])
        ->assertSessionHasErrors('cost');
});

it('forbids payout screens without payouts.view', function () {
    $this->actingAs(OpsSetup::staff(UserRole::Sales))->get(route('admin.pagos-proveedores.index'))->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.pagos-proveedores.index'))->assertOk();
});
