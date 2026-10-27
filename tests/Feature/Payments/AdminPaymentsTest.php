<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Payments\PaymentService;
use App\Services\Visits\VisitCheckoutUrls;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Visits\VisitScenario;

function staffUser(UserRole $role): User
{
    return User::factory()->role($role)->create()->fresh();
}

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make();
    $this->visit = $this->s->book();
    $this->payment = VisitScenario::paymentOf($this->visit);
});

it('finanzas ve el listado con totales por estado calculados en SQL', function () {
    Payment::factory()->paid('trx_a')->create(['amount' => '500.00']);
    Payment::factory()->paid('trx_b')->create(['amount' => '250.50']);

    $this->actingAs(staffUser(UserRole::Finance))
        ->get(route('admin.pagos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/pagos/Index', false)
            ->has('payments.data', 3)
            ->where('totals', fn ($totals) => collect($totals)->firstWhere('status.value', 'paid')['amount'] === '750.50'
                && collect($totals)->firstWhere('status.value', 'pending')['count'] === 1));
});

it('filtra por estado y concepto', function () {
    Payment::factory()->paid('trx_c')->create();

    $this->actingAs(staffUser(UserRole::Finance))
        ->get(route('admin.pagos.index', ['estado' => 'paid']))
        ->assertInertia(fn (Assert $p) => $p->has('payments.data', 1)->where('payments.data.0.status.value', 'paid'));

    $this->actingAs(staffUser(UserRole::Finance))
        ->get(route('admin.pagos.index', ['concepto' => 'visit_fee']))
        ->assertInertia(fn (Assert $p) => $p->has('payments.data', 1)->where('payments.data.0.concept.value', 'visit_fee'));
});

it('un técnico o cliente no entra a finanzas', function () {
    $this->actingAs(staffUser(UserRole::Technician))->get(route('admin.pagos.index'))->assertForbidden();
    $this->actingAs(staffUser(UserRole::Customer))->get(route('admin.pagos.index'))->assertForbidden();
    $this->actingAs(staffUser(UserRole::Design))->get(route('admin.pagos.index'))->assertForbidden();
});

it('el detalle muestra intentos, eventos y webhooks sin datos sensibles', function () {
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxdet', 'order_id' => $r['order_id']])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
    WebhookEvent::factory()->create(['transaction_id' => 'trxdet', 'dedupe_key' => 'charge.succeeded:trxdet:x']);

    $response = $this->actingAs(staffUser(UserRole::Finance))
        ->get(route('admin.pagos.show', $this->payment))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/pagos/Show', false)
            ->has('attempts', 1)
            ->has('webhooks', 1)
            ->where('payment.cardLast4', '1111')
            ->where('can.refund', true)
            ->where('links.visit.folio', $this->visit->folio)
            ->has('links.receipt'));

    expect($response->getContent())->not->toContain(VisitScenario::PRIVATE_KEY);
});

it('registra un pago manual con referencia (payments.record) y agenda la visita', function () {
    $finance = staffUser(UserRole::Finance);

    $this->actingAs($finance)
        ->post(route('admin.pagos.manual', $this->payment), ['method' => 'transfer', 'reference' => 'SPEI 0045821'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->method)->toBe(PaymentMethod::Transfer)
        ->and($payment->provider)->toBe(PaymentProvider::Manual)
        ->and($payment->reference)->toBe('SPEI 0045821')
        ->and($payment->recorded_by)->toBe($finance->id)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::Scheduled)
        ->and(ActivityLog::query()->where('action', 'pago.registro_manual')->exists())->toBeTrue();
});

it('sin payments.record no se puede registrar un pago manual', function () {
    $sales = staffUser(UserRole::Sales);

    $this->actingAs($sales)
        ->post(route('admin.pagos.manual', $this->payment), ['method' => 'cash', 'reference' => 'Recibo 12'])
        ->assertForbidden();

    expect(fn () => app(PaymentService::class)->recordManual($this->payment, PaymentMethod::Cash, 'Recibo 12', $sales))
        ->toThrow(AuthorizationException::class);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('conciliar desde el panel consulta Openpay', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody());

    VisitScenario::fakeOpenpay([
        'GET list' => fn (Request $r) => Factory::response([VisitScenario::transaction(['id' => 'trxpanel', 'order_id' => $r['order_id']])]),
    ]);

    $this->actingAs(staffUser(UserRole::Finance))
        ->post(route('admin.pagos.conciliar', $this->payment))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('diagnóstico de Openpay: solo con openpay.diagnostics y sin revelar secretos', function () {
    WebhookEvent::factory()->create(['event_type' => 'verification', 'dedupe_key' => 'verification:ABC12345', 'transaction_id' => null, 'payload' => ['type' => 'verification', 'verification_code' => 'ABC12345']]);

    $this->actingAs(staffUser(UserRole::Sales))->get(route('admin.openpay.index'))->assertForbidden();
    $this->actingAs(staffUser(UserRole::Sales))->post(route('admin.openpay.probar'))->assertForbidden();

    $response = $this->actingAs(staffUser(UserRole::Finance))
        ->get(route('admin.openpay.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/openpay/Index', false)
            ->where('environment', 'sandbox')
            ->where('configured', true)
            ->where('verification.code', 'ABC12345')
            ->where('webhookUrl', route('webhooks.openpay'))
            ->where('variables.0.hint', '••••0001'));

    $content = $response->getContent();
    expect($content)->not->toContain(VisitScenario::PRIVATE_KEY)
        ->and($content)->not->toContain(VisitScenario::WEBHOOK_PASSWORD)
        ->and($content)->not->toContain(VisitScenario::MERCHANT)
        ->and($content)->not->toContain('pk_test_public_key_123');
});

it('la prueba de conexión hace un GET seguro y guarda el resultado', function () {
    VisitScenario::fakeOpenpay(['GET list' => Factory::response([])]);

    $this->actingAs(staffUser(UserRole::Finance))
        ->post(route('admin.openpay.probar'))
        ->assertRedirect()
        ->assertSessionHas('success');

    VisitScenario::fakeOpenpay(['GET list' => Factory::response(VisitScenario::error(1002, 401), 401)]);

    $this->actingAs(staffUser(UserRole::Finance))
        ->post(route('admin.openpay.probar'))
        ->assertSessionHas('error', 'Credenciales rechazadas: revisa la llave privada y el ambiente (sandbox/producción).');
});

it('comprobante: el cliente A no puede ver el comprobante del cliente B (IDOR)', function () {
    $userA = staffUser(UserRole::Customer);
    $userB = staffUser(UserRole::Customer);
    $customerA = Customer::factory()->create(['user_id' => $userA->id]);
    $customerB = Customer::factory()->create(['user_id' => $userB->id]);
    $paymentA = Payment::factory()->paid('trx_ida')->create(['customer_id' => $customerA->id]);
    $paymentB = Payment::factory()->paid('trx_idb')->create(['customer_id' => $customerB->id]);

    $this->actingAs($userA)->get(route('pagos.comprobante', $paymentA))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('public/pagos/Comprobante', false)->where('receipt.folio', $paymentA->folio)->where('receipt.cardLast4', '1111'));
    $this->actingAs($userA)->get(route('pagos.comprobante', $paymentB))->assertForbidden();

    // Invitado sin firma → login; con firma válida → visible.
    auth()->logout();
    $this->get(route('pagos.comprobante', $paymentB))->assertRedirect(route('login'));
    $this->get(URL::signedRoute('pagos.comprobante', ['payment' => $paymentB->uuid]))->assertOk();

    // Un comprobante de un pago no cubierto no existe.
    $this->actingAs(staffUser(UserRole::Finance))->get(route('pagos.comprobante', $this->payment))->assertNotFound();
});

it('ninguna página de pagos expone la llave privada en las props de Inertia', function () {
    $finance = staffUser(UserRole::Finance);

    foreach ([
        route('admin.pagos.index'),
        route('admin.pagos.show', $this->payment),
        route('admin.openpay.index'),
        route('admin.visitas.show', $this->visit),
    ] as $url) {
        $content = $this->actingAs($finance)->get($url)->assertOk()->getContent();
        expect($content)->not->toContain(VisitScenario::PRIVATE_KEY)->not->toContain(VisitScenario::WEBHOOK_PASSWORD);
    }

    foreach ([VisitCheckoutUrls::pay($this->payment), VisitCheckoutUrls::status($this->payment), route('public.visita.abierta')] as $url) {
        auth()->logout();
        $content = $this->get($url)->assertOk()->getContent();
        expect($content)->not->toContain(VisitScenario::PRIVATE_KEY)->not->toContain(VisitScenario::WEBHOOK_PASSWORD);
    }
});
