<?php

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Enums\VisitCreditStatus;
use App\Events\PaymentRefunded;
use App\Models\ActivityLog;
use App\Models\Refund;
use App\Models\User;
use App\Models\VisitCredit;
use App\Notifications\Visits\VisitStaffAlertNotification;
use App\Payments\PaymentException;
use App\Payments\PaymentService;
use App\Services\Visits\VisitCheckoutUrls;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Feature\Visits\VisitScenario;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make(fn ($f) => $f->creditablePercent('100', 60));
    $this->visit = $this->s->book();
    $this->payment = VisitScenario::paymentOf($this->visit);

    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxref', 'order_id' => $r['order_id']])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);

    $this->finance = User::factory()->role(UserRole::Finance)->create()->fresh();
    $refundId = 0;
    VisitScenario::fakeOpenpay([
        'POST refund' => function (Request $r) use (&$refundId) {
            $refundId++;

            return Factory::response(VisitScenario::transaction(['id' => 'trxref', 'refund' => ['id' => 'trxrefund'.$refundId, 'amount' => $r['amount'], 'status' => 'completed']]));
        },
    ]);
});

it('reembolsa total: estado refunded, monto reembolsado y anula el crédito', function () {
    $this->actingAs($this->finance)
        ->post(route('admin.pagos.reembolsar', $this->payment), ['amount' => '1000.00', 'reason' => 'Cliente canceló la visita', 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refunded_amount)->toBe('1000.00');

    $refund = Refund::query()->sole();
    expect($refund->status)->toBe(RefundStatus::Completed)
        ->and($refund->provider_refund_id)->toBe('trxrefund1');

    $credit = VisitCredit::query()->where('site_visit_id', $this->visit->id)->sole();
    expect($credit->status)->toBe(VisitCreditStatus::Voided)
        ->and($credit->voided_at)->not->toBeNull();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && str_ends_with($r->url(), '/charges/trxref/refund')
        && str_contains($r->body(), '"amount":1000.00'));
});

it('reembolsa parcial, acumula y no permite exceder lo pagado', function () {
    $service = app(PaymentService::class);

    $service->refund($this->payment->fresh(), Money::of('300'), 'Ajuste', (string) Str::uuid(), $this->finance);
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($this->payment->fresh()->refunded_amount)->toBe('300.00');

    // El crédito nunca supera lo efectivamente pagado.
    expect(VisitCredit::query()->sole()->amount)->toBe('700.00')
        ->and(VisitCredit::query()->sole()->status)->toBe(VisitCreditStatus::Available);

    $service->refund($this->payment->fresh(), Money::of('200.50'), 'Ajuste 2', (string) Str::uuid(), $this->finance);
    expect($this->payment->fresh()->refunded_amount)->toBe('500.50');

    expect(fn () => $service->refund($this->payment->fresh(), Money::of('499.51'), 'Excede', (string) Str::uuid(), $this->finance))
        ->toThrow(PaymentException::class);

    $service->refund($this->payment->fresh(), Money::of('499.50'), 'Resto', (string) Str::uuid(), $this->finance);
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->fresh()->refunded_amount)->toBe('1000.00')
        ->and(VisitCredit::query()->sole()->status)->toBe(VisitCreditStatus::Voided);

    expect(fn () => $service->refund($this->payment->fresh(), Money::of('1'), 'Ya no hay saldo', (string) Str::uuid(), $this->finance))
        ->toThrow(PaymentException::class);
});

it('repetir un reembolso con la misma clave es idempotente', function () {
    Event::fake([PaymentRefunded::class]);
    $key = (string) Str::uuid();

    $this->actingAs($this->finance)->post(route('admin.pagos.reembolsar', $this->payment), ['amount' => '400', 'reason' => 'Ajuste comercial', 'idempotency_key' => $key]);
    $this->actingAs($this->finance)->post(route('admin.pagos.reembolsar', $this->payment), ['amount' => '400', 'reason' => 'Ajuste comercial', 'idempotency_key' => $key]);

    expect(Refund::query()->count())->toBe(1)
        ->and($this->payment->fresh()->refunded_amount)->toBe('400.00');
    expect(count(Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/refund'))))->toBe(1);
    Event::assertDispatchedTimes(PaymentRefunded::class, 1);
});

it('muestra error de validación si el monto excede lo disponible', function () {
    $this->actingAs($this->finance)
        ->post(route('admin.pagos.reembolsar', $this->payment), ['amount' => '1000.01', 'reason' => 'Demasiado', 'idempotency_key' => (string) Str::uuid()])
        ->assertSessionHasErrors('amount');

    expect(Refund::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('un reembolso rechazado por Openpay queda como fallido sin tocar el pago', function () {
    VisitScenario::fakeOpenpay(['POST refund' => Factory::response(VisitScenario::error(1013, 412), 412)]);

    $refund = app(PaymentService::class)->refund($this->payment->fresh(), Money::of('100'), 'Prueba', (string) Str::uuid(), $this->finance);

    expect($refund->status)->toBe(RefundStatus::Failed)
        ->and($this->payment->fresh()->refunded_amount)->toBe('0.00')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('solo finanzas puede reembolsar (payments.refund)', function () {
    $sales = User::factory()->role(UserRole::Sales)->create()->fresh();

    $this->actingAs($sales)
        ->post(route('admin.pagos.reembolsar', $this->payment), ['amount' => '100', 'reason' => 'Sin permiso', 'idempotency_key' => (string) Str::uuid()])
        ->assertForbidden();

    expect(fn () => app(PaymentService::class)->refund($this->payment->fresh(), Money::of('100'), 'x', (string) Str::uuid(), $sales))
        ->toThrow(AuthorizationException::class);

    expect(Refund::query()->count())->toBe(0);
});

it('si el crédito ya se aplicó a un contrato no lo toca y avisa a finanzas', function () {
    $credit = VisitCredit::query()->sole();
    // Simula la aplicación hecha por projects (sin crear contrato: se omite la FK para la prueba).
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $credit->forceFill(['status' => VisitCreditStatus::Applied, 'contract_id' => 999999, 'applied_at' => now()])->save();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    app(PaymentService::class)->refund($this->payment->fresh(), Money::of('1000'), 'Reembolso total', (string) Str::uuid(), $this->finance);

    expect($credit->fresh()->status)->toBe(VisitCreditStatus::Applied)
        ->and($credit->fresh()->amount)->toBe('1000.00')
        ->and(ActivityLog::query()->where('action', 'visita.credito_aplicado_reembolsado')->exists())->toBeTrue();

    Notification::assertSentTo($this->finance, VisitStaffAlertNotification::class);
});
