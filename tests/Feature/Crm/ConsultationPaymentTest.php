<?php

use App\Enums\BookingStatus;
use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Models\CalendarBooking;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Payment;
use App\Notifications\Crm\ConsultationAdvisorAlert;
use App\Notifications\Crm\ConsultationBooked;
use App\Services\Crm\ConsultationCheckoutUrls;
use App\Services\Crm\ConsultationHoldExpirer;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Crm\ConsultationSettings;
use App\Support\Crm\CrmLinks;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Crm\CrmSetup;
use Tests\Feature\Visits\VisitScenario;

/*
 * Toda asesoría se cobra con tarjeta (Openpay simulado con Http::fake: nunca
 * se llama al API real). Flujo: apartado + intención → cobro → confirmada.
 */

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    VisitScenario::configureOpenpay();
    // Lunes 2 de noviembre de 2026, 08:00 (hora del centro de México).
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
    $this->advisor = CrmSetup::advisor([2]);
    $this->lead = Lead::factory()->create();

    $this->post(URL::signedRoute('public.asesoria.store', ['lead' => $this->lead->uuid]), [
        'starts_at' => '2026-11-03T10:00:00-06:00',
        'channel' => 'video',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->consultation = Consultation::query()->sole();
    $this->payment = Payment::query()->sole();
});

function consultationChargeOk(string $tx = 'trxasesoria1'): void
{
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => $tx, 'order_id' => $r['order_id'], 'amount' => 500.00])),
        'GET get' => Factory::response(VisitScenario::transaction(['id' => $tx, 'amount' => 500.00])),
    ]);
}

it('booking creates a pending payment and holds the slot', function () {
    expect($this->consultation->status)->toBe(ConsultationStatus::PendingPayment)
        ->and($this->consultation->payment_id)->toBe($this->payment->id)
        ->and($this->consultation->hold_expires_at->equalTo(now()->addMinutes(ConsultationSettings::HOLD_MINUTES)))->toBeTrue()
        ->and($this->payment->status)->toBe(PaymentStatus::Pending)
        ->and($this->payment->amount)->toBe('500.00')
        ->and($this->payment->metadata['next_url'])->toContain('/asesoria/'.$this->consultation->uuid);

    $booking = CalendarBooking::query()->sole();
    expect($booking->status)->toBe(BookingStatus::Held)
        ->and($booking->hold_expires_at->equalTo($this->consultation->hold_expires_at))->toBeTrue();

    // El horario apartado no se ofrece a nadie más.
    $times = collect(app(ConsultationScheduler::class)->slotsForDate(CarbonImmutable::parse('2026-11-03')))->pluck('time');
    expect($times)->not->toContain('10:00');

    // Mismo enlace: no se crea una segunda asesoría, se lleva al pago pendiente.
    $this->get(URL::signedRoute('public.asesoria.create', ['lead' => $this->lead->uuid]))
        ->assertRedirect()
        ->assertRedirectContains('/asesoria/'.$this->consultation->uuid.'/pago');
});

it('renders the secure payment page with the summary and never card data', function () {
    $this->get(ConsultationCheckoutUrls::pay($this->consultation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/asesoria/Pago', false)
            ->where('consultation.folio', $this->consultation->folio)
            ->where('consultation.channel.value', 'video')
            ->where('payment.amount', '500.00')
            ->where('payment.status.value', 'pending')
            ->where('openpay.publicKey', 'pk_test_public_key_123')
            ->missing('openpay.privateKey')
            ->has('chargeUrl'));

    // La pantalla de la asesoría ofrece completar el pago.
    $this->get(CrmLinks::manageConsultation($this->consultation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/asesoria/Show', false)
            ->where('consultation.status.value', 'pending_payment')
            ->where('canChange', false)
            ->where('payUrl', fn ($url) => str_contains((string) $url, '/pago'))
            ->where('receiptUrl', null));
});

it('a successful charge confirms the consultation, the slot and sends the notifications', function () {
    consultationChargeOk();

    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())
        ->assertOk()
        ->assertJson(['status' => 'paid'])
        ->assertJsonPath('nextUrl', fn ($url) => str_contains((string) $url, '/asesoria/'.$this->consultation->uuid));

    // Solo token + device session: Openpay recibe un cargo directo, sin guardar tarjeta ni cliente.
    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/charges')
        && $r['method'] === 'card'
        && str_starts_with((string) $r['source_id'], 'tok')
        && str_contains($r->body(), '"amount":500.00')
        && ! isset($r['card_number'], $r['cvv2']));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/customers') || str_contains($r->url(), '/cards'));

    $payment = $this->payment->fresh();
    $consultation = $this->consultation->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->card_last4)->toBe('1111')
        ->and($consultation->status)->toBe(ConsultationStatus::Confirmed)
        ->and($consultation->paid_at)->not->toBeNull()
        ->and($consultation->hold_expires_at)->toBeNull()
        ->and($consultation->attention_reason)->toBeNull()
        ->and($consultation->booking->status)->toBe(BookingStatus::Confirmed)
        ->and($this->lead->refresh()->status)->toBe(LeadStatus::ConsultationScheduled);

    Notification::assertSentOnDemand(ConsultationBooked::class);
    Notification::assertSentTo($this->advisor, ConsultationAdvisorAlert::class);

    // Pantalla final con comprobante; repetir el cobro no vuelve a cobrar.
    $this->get(CrmLinks::manageConsultation($this->consultation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('consultation.status.value', 'confirmed')
            ->where('payUrl', null)
            ->where('receiptUrl', fn ($url) => str_contains((string) $url, '/comprobante')));

    consultationChargeOk('trxdoble');
    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
    expect(VisitScenario::chargeRequests())->toBe(0);
});

it('a declined charge keeps the hold and allows retrying with another card', function () {
    VisitScenario::fakeOpenpay([
        'POST charge' => Factory::response(VisitScenario::error(3001), 402),
    ]);

    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())
        ->assertOk()
        ->assertJson(['status' => 'failed']);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->consultation->fresh()->status)->toBe(ConsultationStatus::PendingPayment)
        ->and(CalendarBooking::query()->sole()->status)->toBe(BookingStatus::Held);
    Notification::assertNothingSent();

    // La página de pago sigue disponible y muestra el error anterior.
    $this->get(ConsultationCheckoutUrls::pay($this->consultation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('payment.errorMessage', fn ($m) => is_string($m) && $m !== ''));

    consultationChargeOk('trxretry');

    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())
        ->assertJson(['status' => 'paid']);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->fresh()->attempts()->count())->toBe(2)
        ->and($this->consultation->fresh()->status)->toBe(ConsultationStatus::Confirmed);
});

it('returns from 3D Secure through the generic return route and confirms', function () {
    config(['services.openpay.3ds_mode' => 'always']);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction([
            'id' => 'trx3ds', 'order_id' => $r['order_id'], 'status' => 'charge_pending',
            'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/3ds'],
        ])),
    ]);

    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())
        ->assertJson(['status' => 'redirect', 'redirectUrl' => 'https://sandbox-api.openpay.mx/3ds']);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['use_3d_secure'] === true
        && $r['redirect_url'] === route('pagos.retorno', ['payment' => $this->payment->uuid]));

    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trx3ds', 'amount' => 500.00]))]);

    // Openpay agrega ?id=; se ignora y se concilia por API.
    $this->get(route('pagos.retorno', ['payment' => $this->payment->uuid, 'id' => 'manipulado']))
        ->assertRedirect()
        ->assertRedirectContains('/asesoria/'.$this->consultation->uuid);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->consultation->fresh()->status)->toBe(ConsultationStatus::Confirmed);
});

it('the Openpay webhook confirms a pending 3DS consultation payment', function () {
    config(['services.openpay.3ds_mode' => 'always']);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction([
            'id' => 'trxwhc', 'order_id' => $r['order_id'], 'status' => 'charge_pending',
            'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/3ds'],
        ])),
    ]);
    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())->assertJson(['status' => 'redirect']);

    VisitScenario::fakeOpenpay(['GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxwhc', 'amount' => 500.00]))]);

    $this->postJson(route('webhooks.openpay'), [
        'type' => 'charge.succeeded',
        'event_date' => '2026-11-02T08:05:00-06:00',
        'transaction' => VisitScenario::transaction(['id' => 'trxwhc']),
    ], VisitScenario::webhookAuth())->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->consultation->fresh()->status)->toBe(ConsultationStatus::Confirmed);
    Notification::assertSentOnDemand(ConsultationBooked::class);
});

it('hold expiry cancels the consultation, voids the intent and releases the slot', function () {
    $this->travel(ConsultationSettings::HOLD_MINUTES + 1)->minutes();

    $this->artisan('consultations:expire-holds')->assertSuccessful();

    $consultation = $this->consultation->fresh();
    expect($consultation->status)->toBe(ConsultationStatus::Cancelled)
        ->and($consultation->cancel_reason)->toBe(ConsultationHoldExpirer::REASON)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and(CalendarBooking::query()->sole()->status)->toBe(BookingStatus::Released);

    $times = collect(app(ConsultationScheduler::class)->slotsForDate(CarbonImmutable::parse('2026-11-03')))->pluck('time');
    expect($times)->toContain('10:00');

    // Cobrar después del vencimiento no llega a Openpay.
    VisitScenario::fakeOpenpay(['POST charge' => fn () => throw new RuntimeException('no debe cobrar')]);
    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())->assertJson(['status' => 'failed']);
    expect(VisitScenario::chargeRequests())->toBe(0);

    // La página de pago regresa a la asesoría (cancelada, con opción de reagendar).
    $this->get(ConsultationCheckoutUrls::pay($this->consultation))->assertRedirectContains('/asesoria/'.$this->consultation->uuid);

    // Otro cliente puede tomar el horario liberado.
    $other = app(ConsultationScheduler::class)->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 10:00'), ConsultationChannel::Video);
    expect($other->status)->toBe(ConsultationStatus::PendingPayment);
});

it('a charge on an expired hold never charges and sends the customer back to booking', function () {
    $this->travel(ConsultationSettings::HOLD_MINUTES + 1)->minutes();
    VisitScenario::fakeOpenpay(['POST charge' => fn () => throw new RuntimeException('no debe cobrar')]);

    $this->postJson(ConsultationCheckoutUrls::charge($this->consultation), VisitScenario::chargeBody())
        ->assertJson(['status' => 'failed'])
        ->assertJsonPath('nextUrl', fn ($url) => str_contains((string) $url, '/solicitud/'.$this->lead->uuid.'/asesoria'));

    expect($this->consultation->fresh()->status)->toBe(ConsultationStatus::Cancelled)
        ->and(VisitScenario::chargeRequests())->toBe(0);
});

it('a late payment on an expired hold is kept and flagged for the team', function () {
    $this->travel(ConsultationSettings::HOLD_MINUTES + 1)->minutes();
    $this->artisan('consultations:expire-holds')->assertSuccessful();

    // Openpay confirma tarde un cobro (p. ej. 3DS completado al límite).
    $this->payment->forceFill(['status' => PaymentStatus::Pending])->save();
    CrmSetup::pay($this->consultation);

    $consultation = $this->consultation->fresh();
    expect($consultation->status)->toBe(ConsultationStatus::Cancelled)
        ->and($consultation->paid_at)->not->toBeNull()
        ->and($consultation->attention_reason)->toContain('reembolsa');
});

it('cancelling an unpaid consultation voids the payment intent', function () {
    $this->post(URL::signedRoute('public.asesoria.cancelar', ['consultation' => $this->consultation->uuid]), ['reason' => 'Cambio de planes'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->consultation->fresh()->status)->toBe(ConsultationStatus::Cancelled)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
});

it('customers cannot reschedule before paying and staff cannot mark it confirmed without payment', function () {
    $this->post(URL::signedRoute('public.asesoria.reprogramar', ['consultation' => $this->consultation->uuid]), [
        'starts_at' => '2026-11-03T11:00:00-06:00',
    ])->assertSessionHasErrors('starts_at');

    $this->actingAs($this->advisor)
        ->patch(route('admin.asesorias.estado', $this->consultation), ['status' => 'confirmed'])
        ->assertSessionHasErrors('status');

    expect($this->consultation->fresh()->status)->toBe(ConsultationStatus::PendingPayment);
});

it('asks for an email when the lead has none (needed for the receipt and the charge)', function () {
    $lead = Lead::factory()->create();
    Customer::query()->whereKey($lead->customer_id)->update(['email' => null]);

    $this->get(URL::signedRoute('public.asesoria.create', ['lead' => $lead->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('needsEmail', true)->where('settings.isFree', false)->where('settings.price', '500.00'));

    $url = URL::signedRoute('public.asesoria.store', ['lead' => $lead->uuid]);
    $this->post($url, ['starts_at' => '2026-11-03T11:00:00-06:00', 'channel' => 'phone'])->assertSessionHasErrors('email');
    $this->post($url, ['starts_at' => '2026-11-03T11:00:00-06:00', 'channel' => 'phone', 'email' => 'Nuevo@Example.com'])->assertSessionHasNoErrors();

    expect(Customer::query()->find($lead->customer_id)->email)->toBe('nuevo@example.com');
});

it('the price is always positive: a stored 0 falls back to the default and is frozen on the consultation', function () {
    Settings::put('consultation', ['price' => '0.00']);
    expect(ConsultationSettings::current()->price->amount)->toBe(ConsultationSettings::DEFAULT_PRICE)
        ->and(ConsultationSettings::current()->isFree())->toBeFalse();

    Settings::put('consultation', ['price' => '750.00']);
    $c = app(ConsultationScheduler::class)->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 12:00'), ConsultationChannel::Phone);
    expect($c->price)->toBe('750.00')
        ->and($c->payment->amount)->toBe('750.00');

    // Cambiar el precio después no altera lo ya apartado.
    Settings::put('consultation', ['price' => '900.00']);
    expect($c->fresh()->payment->amount)->toBe('750.00');
});

it('links every consultation payment to the customer so the portal can list them', function () {
    CrmSetup::pay($this->consultation);

    $payments = Payment::query()->where('customer_id', $this->lead->customer_id)->with('consultation')->get();

    expect($payments)->toHaveCount(1)
        ->and($payments->first()->consultation->is($this->consultation))->toBeTrue()
        ->and($this->consultation->fresh()->payment->is($payments->first()))->toBeTrue();
});

it('shows the payment status in the admin list and detail', function () {
    $this->actingAs($this->advisor)
        ->get(route('admin.asesorias.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/asesorias/Index')
            ->where('consultations.data.0.payment.status.value', 'pending')
            ->where('consultations.data.0.status.value', 'pending_payment'));

    $this->actingAs($this->advisor)
        ->get(route('admin.asesorias.show', $this->consultation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/asesorias/Show')
            ->where('payment.amount', '500.00')
            ->where('payment.status.value', 'pending')
            ->where('payUrl', fn ($url) => str_contains((string) $url, '/pago')));
});

it('staff rescheduling an unpaid consultation keeps it pending with the same payment intent', function () {
    $this->actingAs($this->advisor)
        ->post(route('admin.asesorias.reprogramar', $this->consultation), ['starts_at' => '2026-11-03 11:00'])
        ->assertSessionHasNoErrors();

    $new = Consultation::query()->where('rescheduled_from_id', $this->consultation->id)->sole();
    expect($new->status)->toBe(ConsultationStatus::PendingPayment)
        ->and($new->payment_id)->toBe($this->payment->id)
        ->and($new->booking->status)->toBe(BookingStatus::Held)
        ->and(Payment::query()->count())->toBe(1);

    // Al pagar se confirma la cita vigente (no la reprogramada).
    CrmSetup::pay($this->consultation);
    expect($new->fresh()->status)->toBe(ConsultationStatus::Confirmed)
        ->and($this->consultation->fresh()->status)->toBe(ConsultationStatus::Rescheduled);
});
