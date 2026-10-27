<?php

use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Enums\WebhookEventStatus;
use App\Events\PaymentConfirmed;
use App\Models\PaymentAttempt;
use App\Models\WebhookEvent;
use App\Services\Visits\VisitCheckoutUrls;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Visits\VisitScenario;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make();
    $this->visit = $this->s->book();
    $this->payment = VisitScenario::paymentOf($this->visit);
});

/** Deja el pago en processing con un intento cuyo id de transacción es $tx (p. ej. 3DS pendiente). */
function pendingCharge($test, string $tx): void
{
    config(['services.openpay.3ds_mode' => 'always']);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction([
            'id' => $tx, 'order_id' => $r['order_id'], 'status' => 'charge_pending',
            'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/3ds'],
        ])),
    ]);
    $test->postJson(VisitCheckoutUrls::charge($test->payment), VisitScenario::chargeBody())->assertJson(['status' => 'redirect']);
}

function webhookPayload(string $type, string $tx, array $transaction = []): array
{
    return [
        'type' => $type,
        'event_date' => '2026-10-27T10:00:00-06:00',
        'transaction' => VisitScenario::transaction(['id' => $tx] + $transaction),
    ];
}

it('rechaza un webhook con credenciales Basic incorrectas (401)', function () {
    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxany'), VisitScenario::webhookAuth('openpay-hook', 'mala'))
        ->assertUnauthorized();
    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxany'))
        ->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('rechaza todo si el webhook no está configurado', function () {
    config(['services.openpay.webhook_password' => '']);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxany'), VisitScenario::webhookAuth('openpay-hook', ''))
        ->assertUnauthorized();
});

it('excluye la verificación CSRF solo en la ruta del webhook', function () {
    $route = Route::getRoutes()->getByName('webhooks.openpay');

    expect($route->excludedMiddleware())->toContain(ValidateCsrfToken::class)
        ->and(Route::getRoutes()->getByName('public.visita.cobrar')->excludedMiddleware())->not->toContain(ValidateCsrfToken::class);
});

// Regresión (E2E): Laravel 13 protege el grupo web con PreventRequestForgery
// (padre de ValidateCsrfToken). Excluir solo ValidateCsrfToken no la quitaba
// y Openpay recibía 419. En Pest el CSRF se omite (runningUnitTests), así que
// se revisa la pila de middleware ya resuelta de cada ruta.
it('la pila real del webhook no incluye la protección CSRF del grupo web', function () {
    $this->get('/up'); // registra los grupos de middleware del kernel HTTP en el router
    $router = app('router');
    $stack = function (string $name) use ($router): array {
        $route = Route::getRoutes()->getByName($name);

        return $router->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());
    };
    $webhook = $stack('webhooks.openpay');
    $charge = $stack('public.visita.cobrar');

    expect($webhook)->not->toContain(PreventRequestForgery::class)
        ->and($webhook)->not->toContain(ValidateCsrfToken::class)
        ->and($charge)->toContain(PreventRequestForgery::class);
});

it('guarda el código de verificación para el diagnóstico', function () {
    $this->postJson(route('webhooks.openpay'), ['type' => 'verification', 'event_date' => '2026-10-27T10:00:00-06:00', 'verification_code' => 'UY1qqrxw'], VisitScenario::webhookAuth())
        ->assertOk();

    $event = WebhookEvent::query()->sole();
    expect($event->event_type)->toBe('verification')
        ->and($event->payload['verification_code'])->toBe('UY1qqrxw')
        ->and($event->status)->toBe(WebhookEventStatus::Processed);
});

it('charge.succeeded re-consulta el API y confirma el pago', function () {
    pendingCharge($this, 'trxwh1');
    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxwh1']))]);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxwh1'), VisitScenario::webhookAuth())
        ->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->visit->fresh()->status)->toBe(VisitStatus::Scheduled)
        ->and(WebhookEvent::query()->sole()->status)->toBe(WebhookEventStatus::Processed);

    Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), '/charges/trxwh1'));
});

it('el payload del webhook no es prueba de pago: si el API dice pendiente, sigue pendiente', function () {
    pendingCharge($this, 'trxfake');
    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxfake', 'status' => 'charge_pending']))]);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxfake'), VisitScenario::webhookAuth())->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Processing);
});

it('un webhook duplicado se procesa una sola vez', function () {
    Event::fake([PaymentConfirmed::class]);
    pendingCharge($this, 'trxdup');
    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxdup']))]);

    $payload = webhookPayload('charge.succeeded', 'trxdup');
    $this->postJson(route('webhooks.openpay'), $payload, VisitScenario::webhookAuth())->assertOk()->assertJsonMissing(['duplicate' => true]);
    $this->postJson(route('webhooks.openpay'), $payload, VisitScenario::webhookAuth())->assertOk()->assertJson(['duplicate' => true]);
    $this->postJson(route('webhooks.openpay'), $payload, VisitScenario::webhookAuth())->assertOk()->assertJson(['duplicate' => true]);

    expect(WebhookEvent::query()->count())->toBe(1);
    Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
    expect(count(Http::recorded(fn (Request $r) => $r->method() === 'GET')))->toBe(1);
});

it('un webhook de una transacción desconocida se guarda y se ignora sin error', function () {
    VisitScenario::fakeOpenpay([]);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxdesconocida', ['order_id' => 'OTRO-SISTEMA-1']), VisitScenario::webhookAuth())
        ->assertOk();

    $event = WebhookEvent::query()->sole();
    expect($event->status)->toBe(WebhookEventStatus::Ignored)
        ->and($event->transaction_id)->toBe('trxdesconocida')
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);

    Http::assertNothingSent();
});

it('localiza el pago por order_id cuando el intento quedó sin respuesta', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'processing']);

    $orderId = $this->payment->order_id.'-1';
    VisitScenario::fakeOpenpay([
        'GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxbyorder', 'order_id' => $orderId])),
        'GET list' => Factory::response([VisitScenario::transaction(['id' => 'trxbyorder', 'order_id' => $orderId])]),
    ]);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxbyorder', ['order_id' => $orderId]), VisitScenario::webhookAuth())->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(PaymentAttempt::query()->where('payment_id', $this->payment->id)->sole()->provider_transaction_id)->toBe('trxbyorder');
});

it('chargeback.created deja el pago en contracargo y chargeback.rejected lo restablece', function () {
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxcb', 'order_id' => $r['order_id']])),
    ]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);

    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxcb', 'status' => 'chargeback_pending']))]);
    $this->postJson(route('webhooks.openpay'), webhookPayload('chargeback.created', 'trxcb'), VisitScenario::webhookAuth())->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Disputed);

    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxcb', 'status' => 'completed']))]);
    $payload = webhookPayload('chargeback.rejected', 'trxcb');
    $payload['event_date'] = '2026-11-15T10:00:00-06:00';
    $this->postJson(route('webhooks.openpay'), $payload, VisitScenario::webhookAuth())->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('guarda el payload sin datos de tarjeta completos ni titular', function () {
    VisitScenario::fakeOpenpay([]);

    $this->postJson(route('webhooks.openpay'), webhookPayload('charge.succeeded', 'trxpii', ['card' => ['card_number' => '4111111111111111', 'holder_name' => 'Ana Lopez']]), VisitScenario::webhookAuth())
        ->assertOk();

    $stored = json_encode(WebhookEvent::query()->sole()->payload);
    expect($stored)->not->toContain('4111111111111111')
        ->and($stored)->not->toContain('Ana Lopez')
        ->and($stored)->toContain('1111');
});
