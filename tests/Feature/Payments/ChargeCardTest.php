<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\VisitCreditStatus;
use App\Enums\VisitStatus;
use App\Events\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\CalendarBooking;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\VisitCredit;
use App\Payments\PaymentService;
use App\Services\Visits\VisitCheckoutUrls;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Visits\VisitScenario;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make(fn ($f) => $f->creditablePercent('100', 60));
    $this->visit = $this->s->book();
    $this->payment = VisitScenario::paymentOf($this->visit);
});

it('cobra con éxito: pago pagado, visita agendada y crédito creado', function () {
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxok1', 'order_id' => $r['order_id']])),
    ]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertOk()
        ->assertJson(['status' => 'paid']);

    $payment = $this->payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->provider_transaction_id)->toBe('trxok1')
        ->and($payment->card_last4)->toBe('1111')
        ->and($payment->card_brand)->toBe('visa')
        ->and($payment->provider_authorization)->toBe('801585');

    $visit = $this->visit->fresh();
    expect($visit->status)->toBe(VisitStatus::Scheduled)
        ->and($visit->paid_at)->not->toBeNull()
        ->and($visit->hold_expires_at)->toBeNull();

    expect(CalendarBooking::query()->where('bookable_id', $visit->id)->first()->status)->toBe(BookingStatus::Confirmed);

    $credit = VisitCredit::query()->where('site_visit_id', $visit->id)->first();
    expect($credit)->not->toBeNull()
        ->and($credit->status)->toBe(VisitCreditStatus::Available)
        ->and($credit->amount)->toBe('1000.00')
        ->and($credit->expires_on->toDateString())->toBe(now()->addDays(60)->toDateString());

    // Petición correcta a Openpay: Basic con llave privada, X-Forwarded-For, monto decimal exacto, order_id por intento.
    Http::assertSent(function (Request $r) {
        return $r->method() === 'POST'
            && str_contains($r->url(), 'https://sandbox-api.openpay.mx/v1/'.VisitScenario::MERCHANT.'/charges')
            && $r->hasHeader('X-Forwarded-For', '127.0.0.1')
            && $r->hasHeader('Authorization', 'Basic '.base64_encode(VisitScenario::PRIVATE_KEY.':'))
            && str_contains($r->body(), '"amount":1000.00')
            && $r['method'] === 'card'
            && $r['currency'] === 'MXN'
            && $r['order_id'] === $this->payment->order_id.'-1'
            && $r['customer']['email'] !== null
            && ! isset($r['use_3d_secure']);
    });
});

it('despacha PaymentConfirmed una sola vez', function () {
    Event::fake([PaymentConfirmed::class]);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxonce', 'order_id' => $r['order_id']])),
        'GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxonce'])),
    ]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
    app(PaymentService::class)->reconcile($this->payment->fresh());

    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
});

it('un rechazo 3001 deja la visita pendiente con su apartado y un mensaje seguro', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::response(VisitScenario::error(3001), 402)]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertOk()
        ->assertJson([
            'status' => 'failed',
            'message' => 'Tu banco no autorizó el cargo. Intenta con otra tarjeta o comunícate con tu banco.',
        ])
        ->assertJsonMissing(['message' => 'The card was declined by the bank']);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->payment->fresh()->error_code)->toBe('3001');

    $visit = $this->visit->fresh();
    expect($visit->status)->toBe(VisitStatus::PendingPayment)
        ->and($visit->holdIsActive())->toBeTrue()
        ->and(CalendarBooking::query()->where('bookable_id', $visit->id)->first()->status)->toBe(BookingStatus::Held);

    // Puede reintentar con otra tarjeta (nuevo intento, nuevo order_id).
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxretry', 'order_id' => $r['order_id']])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['order_id'] === $this->payment->order_id.'-2');
});

it('un doble clic con la misma clave de intento envía un solo cargo', function () {
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxdbl', 'order_id' => $r['order_id']])),
    ]);

    $body = VisitScenario::chargeBody('7f1d2c3b-4a5e-4f60-8a9b-0c1d2e3f4a5b');

    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'paid']);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'paid']);

    expect(VisitScenario::chargeRequests())->toBe(1)
        ->and(PaymentAttempt::query()->where('payment_id', $this->payment->id)->count())->toBe(1);
});

it('la misma clave mientras el primer intento sigue en curso no cobra otra vez', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);

    $body = VisitScenario::chargeBody();
    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'processing']);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'processing']);

    expect(VisitScenario::chargeRequests())->toBe(1);
});

it('un reintento tras timeout concilia por order_id en lugar de cobrar dos veces', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertJson(['status' => 'processing']);

    $attempt = PaymentAttempt::query()->where('payment_id', $this->payment->id)->first();
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($attempt->status)->toBe(PaymentAttemptStatus::Unknown)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::PaymentProcessing);

    // El cargo SÍ se creó en Openpay: el cliente recarga y vuelve a enviar con otra clave.
    $orderId = $this->payment->order_id.'-1';
    VisitScenario::fakeOpenpay([
        'GET list' => fn (Request $r) => Factory::response($r['order_id'] === $orderId ? [VisitScenario::transaction(['id' => 'trxlate', 'order_id' => $orderId])] : []),
        'POST charge' => Factory::response(VisitScenario::error(1006, 409), 409),
    ]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertJson(['status' => 'paid']);

    expect(VisitScenario::chargeRequests())->toBe(0)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->fresh()->provider_transaction_id)->toBe('trxlate')
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::Scheduled)
        ->and(PaymentAttempt::query()->where('payment_id', $this->payment->id)->count())->toBe(1);
});

it('un timeout sin cargo en Openpay permite reintentar tras el margen', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'processing']);

    // Openpay no lo registró. Dentro del margen: no se permite otro cargo.
    VisitScenario::fakeOpenpay(['GET list' => Factory::response([])]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'processing']);
    expect(VisitScenario::chargeRequests())->toBe(0);

    // Pasado el margen se da por no registrado y se permite un intento nuevo.
    $this->travel(5)->minutes();
    VisitScenario::fakeOpenpay([
        'GET list' => Factory::response([]),
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxsecond', 'order_id' => $r['order_id']])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);

    expect(VisitScenario::chargeRequests())->toBe(1)
        ->and(PaymentAttempt::query()->where('payment_id', $this->payment->id)->orderBy('number')->pluck('status')->map->value->all())
        ->toBe(['expired', 'approved']);
});

it('flujo 3D Secure: charge_pending → retorno → conciliación → pagado', function () {
    config(['services.openpay.3ds_mode' => 'always']);
    $redirect = 'https://sandbox-api.openpay.mx/v1/'.VisitScenario::MERCHANT.'/charges/trx3ds/redirect/';

    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction([
            'id' => 'trx3ds', 'order_id' => $r['order_id'], 'status' => 'charge_pending', 'authorization' => null,
            'payment_method' => ['type' => 'redirect', 'url' => $redirect],
        ])),
    ]);

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertJson(['status' => 'redirect', 'redirectUrl' => $redirect]);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && $r['use_3d_secure'] === true
        && $r['redirect_url'] === route('public.visita.retorno', ['payment' => $this->payment->uuid]));

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::PaymentProcessing);

    // El banco autenticó: Openpay redirige con ?id= y nosotros consultamos el API.
    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trx3ds']))]);

    $this->get(route('public.visita.retorno', ['payment' => $this->payment->uuid, 'id' => 'trx3ds']))
        ->assertRedirect();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::Scheduled);
    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/charges/trx3ds'));
});

it('el retorno 3DS nunca confía en el id de la URL', function () {
    config(['services.openpay.3ds_mode' => 'always']);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction([
            'id' => 'trx3dsx', 'order_id' => $r['order_id'], 'status' => 'charge_pending',
            'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/redirect'],
        ])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody());

    // El banco no autenticó: el API sigue en charge_pending → no se marca pagado.
    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trx3dsx', 'status' => 'charge_pending']))]);
    $this->get(route('public.visita.retorno', ['payment' => $this->payment->uuid, 'id' => 'trx3dsx']));

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($this->visit->fresh()->paid_at)->toBeNull();
});

it('autenticación selectiva: un 3005 se reintenta con 3D Secure usando el mismo token', function () {
    $calls = 0;
    VisitScenario::fakeOpenpay([
        'POST charge' => function (Request $r) use (&$calls) {
            $calls++;

            return $calls === 1
                ? Factory::response(VisitScenario::error(3005), 402)
                : Factory::response(VisitScenario::transaction(['id' => 'trxsel', 'order_id' => $r['order_id'], 'status' => 'charge_pending', 'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/3ds']]));
        },
    ]);

    $body = VisitScenario::chargeBody();
    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'redirect']);

    $sent = Http::recorded(fn (Request $r) => $r->method() === 'POST');
    expect($sent)->toHaveCount(2)
        ->and($sent[0][0]['source_id'])->toBe($body['token_id'])
        ->and($sent[1][0]['source_id'])->toBe($body['token_id'])
        ->and($sent[1][0]['use_3d_secure'])->toBeTrue()
        ->and($sent[1][0]['order_id'])->toBe($this->payment->order_id.'-2');
});

it('no cobra si el apartado ya venció y libera el horario', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::response(VisitScenario::transaction())]);
    $this->travel(20)->minutes();

    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())
        ->assertJson(['status' => 'failed']);

    expect(VisitScenario::chargeRequests())->toBe(0)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::Expired)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('rechaza la petición de cobro sin firma válida', function () {
    $this->postJson(route('public.visita.cobrar', ['payment' => $this->payment->uuid]), VisitScenario::chargeBody())
        ->assertForbidden();
});

it('nunca registra números de tarjeta, tokens ni la llave privada en bitácoras', function () {
    Log::spy();
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxlog', 'order_id' => $r['order_id'], 'card' => ['card_number' => '4111111111111111']])),
    ]);

    $body = VisitScenario::chargeBody();
    $this->postJson(VisitCheckoutUrls::charge($this->payment), $body)->assertJson(['status' => 'paid']);

    $dump = json_encode([
        ActivityLog::query()->get()->toArray(),
        PaymentEvent::query()->get()->toArray(),
        PaymentAttempt::query()->get()->toArray(),
        $this->payment->fresh()->toArray(),
    ]);

    expect($dump)->not->toContain('4111111111111111')
        ->and($dump)->not->toContain($body['token_id'])
        ->and($dump)->not->toContain($body['device_session_id'])
        ->and($dump)->not->toContain(VisitScenario::PRIVATE_KEY)
        ->and($this->payment->fresh()->card_last4)->toBe('1111');

    Log::shouldNotHaveReceived('info', [Mockery::any(), Mockery::on(fn ($ctx) => str_contains(json_encode($ctx), '4111111111111111') || str_contains(json_encode($ctx), $body['token_id']))]);
});
