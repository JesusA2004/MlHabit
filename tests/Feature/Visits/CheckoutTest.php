<?php

use App\Enums\BookingStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Enums\VisitStatus;
use App\Models\CalendarBooking;
use App\Models\CoverageZone;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Models\VisitPolicy;
use App\Services\Scheduling\SlotUnavailableException;
use App\Services\Visits\VisitBookingException;
use App\Services\Visits\VisitBookingService;
use App\Services\Visits\VisitCheckoutUrls;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Visits\VisitScenario;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('sin política activa el checkout queda cerrado', function () {
    VisitScenario::configureOpenpay();

    $this->get(route('public.visita.abierta'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/visita/Agendar', false)->where('policy', null));

    $this->postJson(route('public.visita.cobertura'), ['postal_code' => '06700'])
        ->assertOk()
        ->assertJson(['covered' => false, 'closed' => true, 'message' => 'Por ahora agenda tu visita con un asesor.']);

    $this->post(route('public.visita.reservar'), VisitScenario::bookingData())
        ->assertSessionHasErrors('form');

    expect(SiteVisit::query()->count())->toBe(0);
});

it('rechaza un código postal sin cobertura con un mensaje amable', function () {
    VisitScenario::make();

    $this->postJson(route('public.visita.cobertura'), ['postal_code' => '99999'])
        ->assertOk()
        ->assertJson(['covered' => false, 'closed' => false])
        ->assertJsonPath('dates', []);

    $this->post(route('public.visita.reservar'), VisitScenario::bookingData(['postal_code' => '99999']))
        ->assertSessionHasErrors('postal_code');

    expect(SiteVisit::query()->count())->toBe(0);
});

it('una zona sin visitas habilitadas no acepta reservas', function () {
    $s = VisitScenario::make();
    $s->zone->update(['visits_enabled' => false]);

    $this->postJson(route('public.visita.cobertura'), ['postal_code' => VisitScenario::POSTAL_CODE])
        ->assertJson(['covered' => false]);
});

it('con cobertura devuelve fechas y horarios disponibles', function () {
    VisitScenario::make();

    $res = $this->postJson(route('public.visita.cobertura'), ['postal_code' => VisitScenario::POSTAL_CODE])
        ->assertOk()
        ->assertJson(['covered' => true, 'zone' => ['name' => 'Zona Roma']]);

    expect($res->json('dates'))->toContain(VisitScenario::day());

    $slots = $this->getJson(route('public.visita.horarios', ['cp' => VisitScenario::POSTAL_CODE, 'fecha' => VisitScenario::day()]))
        ->assertOk()
        ->json('slots');

    // 08:00–18:00, visitas de 90 min cada 30 min → primera 08:00, última 16:30.
    expect($slots[0])->toBe(['start' => '08:00', 'end' => '09:30'])
        ->and(end($slots))->toBe(['start' => '16:30', 'end' => '18:00']);
});

it('reserva: aparta el horario, congela la tarifa y crea la intención de cobro', function () {
    $s = VisitScenario::make(fn ($f) => $f->state(['fee_amount' => '1500.00', 'hold_minutes' => 15])->creditableFixed('1000.00', 45));
    $data = VisitScenario::bookingData();

    $response = $this->post(route('public.visita.reservar'), $data);

    $visit = SiteVisit::query()->sole();
    $payment = Payment::query()->sole();

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/agendar-visita/pago/'.$payment->uuid)->toContain('signature=');

    expect($visit->status)->toBe(VisitStatus::PendingPayment)
        ->and($visit->fee_amount)->toBe('1500.00')
        ->and($visit->is_creditable)->toBeTrue()
        ->and($visit->credit_amount)->toBe('1000.00')
        ->and($visit->visit_policy_id)->toBe($s->policy->id)
        ->and($visit->technician_id)->toBe($s->technician->id)
        ->and($visit->hold_expires_at->between(now()->addMinutes(14), now()->addMinutes(16)))->toBeTrue()
        ->and($visit->starts_at->format('Y-m-d H:i'))->toBe($data['date'].' 10:00')
        ->and($visit->ends_at->format('H:i'))->toBe('11:30');

    expect($payment->concept)->toBe(PaymentConcept::VisitFee)
        ->and($payment->amount)->toBe('1500.00')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->site_visit_id)->toBe($visit->id)
        ->and($payment->order_id)->toMatch('/^MLH-[0-9A-F]{12}$/');

    $booking = CalendarBooking::query()->sole();
    expect($booking->status)->toBe(BookingStatus::Held)
        ->and($booking->bookable_id)->toBe($visit->id);

    // Prospecto creado por el CRM (origen web, etapa visita por agendar).
    if (class_exists(Lead::class)) {
        $lead = Lead::query()->find($visit->lead_id);
        expect($lead)->not->toBeNull()->and($lead->status)->toBe(LeadStatus::VisitPending);
    }
});

it('la misma clave de idempotencia devuelve la misma visita', function () {
    VisitScenario::make();
    $data = VisitScenario::bookingData();

    $this->post(route('public.visita.reservar'), $data)->assertRedirect();
    $this->post(route('public.visita.reservar'), $data)->assertRedirect();

    expect(SiteVisit::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(CalendarBooking::query()->count())->toBe(1);
});

it('dos clientes que reservan el mismo horario: uno obtiene SlotUnavailable', function () {
    $s = VisitScenario::make();

    $first = $s->book(['email' => 'uno@example.com']);
    expect($first->status)->toBe(VisitStatus::PendingPayment);

    expect(fn () => $s->book(['email' => 'dos@example.com', 'phone' => '5587654321']))
        ->toThrow(SlotUnavailableException::class);

    $this->post(route('public.visita.reservar'), VisitScenario::bookingData(['email' => 'tres@example.com', 'phone' => '5511112222']))
        ->assertSessionHasErrors('start');

    expect(SiteVisit::query()->count())->toBe(1)
        ->and(CalendarBooking::query()->count())->toBe(1);
});

it('con dos técnicos el mismo horario se asigna al menos cargado', function () {
    $s = VisitScenario::make(technicians: 2);

    $a = $s->book(['email' => 'a@example.com']);
    $b = $s->book(['email' => 'b@example.com', 'phone' => '5500000001']);

    expect($a->technician_id)->not->toBe($b->technician_id);
    expect(fn () => $s->book(['email' => 'c@example.com', 'phone' => '5500000002']))->toThrow(SlotUnavailableException::class);
});

it('respeta el cupo diario de la zona', function () {
    $s = VisitScenario::make(technicians: 2);
    $s->zone->update(['max_visits_per_day' => 1]);

    $s->book();

    $slots = $this->getJson(route('public.visita.horarios', ['cp' => VisitScenario::POSTAL_CODE, 'fecha' => VisitScenario::day()]))->json('slots');
    expect($slots)->toBe([]);
    expect(fn () => $s->book(['email' => 'otra@example.com', 'phone' => '5500000003', 'start' => '14:00']))->toThrow(SlotUnavailableException::class);
});

it('el vencimiento del apartado libera el horario', function () {
    $s = VisitScenario::make();
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    $this->travel(16)->minutes();
    $this->artisan('visits:expire-holds')->assertSuccessful();

    expect($visit->fresh()->status)->toBe(VisitStatus::Expired)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and(CalendarBooking::query()->sole()->status)->toBe(BookingStatus::Released);

    // Otro cliente ya puede tomar el mismo horario.
    $other = $s->book(['email' => 'nuevo@example.com', 'phone' => '5599999999']);
    expect($other->status)->toBe(VisitStatus::PendingPayment);
});

it('no vence un apartado cuyo pago sí entró en Openpay (concilia primero)', function () {
    $s = VisitScenario::make();
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);
    $this->postJson(VisitCheckoutUrls::charge($payment), VisitScenario::chargeBody())->assertJson(['status' => 'processing']);

    $this->travel(16)->minutes();
    VisitScenario::fakeOpenpay([
        'GET list' => fn (Request $r) => Factory::response([VisitScenario::transaction(['id' => 'trxlate2', 'order_id' => $r['order_id']])]),
    ]);
    $this->artisan('visits:expire-holds')->assertSuccessful();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($visit->fresh()->status)->toBe(VisitStatus::Scheduled)
        ->and($visit->fresh()->attention_reason)->toBeNull();
});

it('pago tardío con el horario ya tomado: visita pagada que requiere atención', function () {
    $s = VisitScenario::make();
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    VisitScenario::fakeOpenpay(['POST charge' => Factory::failedConnection()]);
    $this->postJson(VisitCheckoutUrls::charge($payment), VisitScenario::chargeBody());

    // El apartado vence y otro cliente toma el horario.
    $this->travel(16)->minutes();
    $s->book(['email' => 'rapido@example.com', 'phone' => '5544443333']);

    VisitScenario::fakeOpenpay([
        'GET list' => fn (Request $r) => Factory::response([VisitScenario::transaction(['id' => 'trxlate3', 'order_id' => $r['order_id']])]),
    ]);
    $this->artisan('payments:reconcile', ['--minutes' => 0])->assertSuccessful();

    $visit->refresh();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($visit->paid_at)->not->toBeNull()
        ->and($visit->attention_reason)->not->toBeNull();
});

it('cambiar la política después de reservar no altera la visita', function () {
    $s = VisitScenario::make(fn ($f) => $f->state(['fee_amount' => '1000.00'])->creditablePercent('50', 30));
    $visit = $s->book();

    $new = VisitPolicy::factory()->create(['fee_amount' => '1500.00', 'is_creditable' => false]);
    $s->policy->update(['is_active' => false]);
    $new->update(['is_active' => true]);

    $visit->refresh();
    expect($visit->fee_amount)->toBe('1000.00')
        ->and($visit->credit_amount)->toBe('500.00')
        ->and($visit->visit_policy_id)->toBe($s->policy->id)
        ->and(VisitScenario::paymentOf($visit)->amount)->toBe('1000.00');

    $next = $s->book(['email' => 'despues@example.com', 'phone' => '5533332222', 'start' => '14:00']);
    expect($next->fee_amount)->toBe('1500.00')->and($next->is_creditable)->toBeFalse();
});

it('el enlace firmado del prospecto precarga sus datos y lo vincula', function () {
    if (! class_exists(Lead::class)) {
        $this->markTestSkipped('Módulo crm no disponible.');
    }

    VisitScenario::make();
    $lead = Lead::factory()->create(['postal_code' => VisitScenario::POSTAL_CODE, 'status' => LeadStatus::Contacted]);

    $this->get(route('public.visita.inicio', ['lead' => $lead->uuid]))->assertForbidden();

    $page = $this->get(VisitCheckoutUrls::forLead($lead))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('public/visita/Agendar', false)
            ->where('prefill.postalCode', VisitScenario::POSTAL_CODE)
            ->where('prefill.leadFolio', $lead->folio)
            ->has('prefill.leadToken'));

    $token = $page->viewData('page')['props']['prefill']['leadToken'];

    $this->post(route('public.visita.reservar'), VisitScenario::bookingData(['lead_token' => $token]))->assertRedirect();

    $visit = SiteVisit::query()->sole();
    expect($visit->lead_id)->toBe($lead->id)
        ->and($visit->customer_id)->toBe($lead->customer_id);
});

it('la página de pago exige firma y no expone la llave privada', function () {
    $s = VisitScenario::make();
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    $this->get(route('public.visita.pago', ['payment' => $payment->uuid]))->assertForbidden();

    $response = $this->get(VisitCheckoutUrls::pay($payment))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('public/visita/Pago', false)
            ->where('openpay.merchantId', VisitScenario::MERCHANT)
            ->where('openpay.publicKey', 'pk_test_public_key_123')
            ->where('openpay.sandbox', true)
            ->where('payment.amount', '1000.00')
            ->has('visit.holdExpiresAt'));

    expect($response->getContent())->not->toContain(VisitScenario::PRIVATE_KEY)
        ->and($response->getContent())->not->toContain(VisitScenario::WEBHOOK_PASSWORD);
});

it('la página de estado nunca muestra éxito sin confirmación del backend', function () {
    $s = VisitScenario::make();
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    $this->get(VisitCheckoutUrls::status($payment))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('public/visita/Estado', false)
            ->where('payment.status.value', 'pending')
            ->where('receiptUrl', null));
});

it('valida los datos del formulario en español', function () {
    VisitScenario::make();

    $this->post(route('public.visita.reservar'), VisitScenario::bookingData(['email' => 'no-es-correo', 'terms' => false, 'phone' => '12']))
        ->assertSessionHasErrors(['email', 'terms', 'phone']);
});

it('toda visita se cobra: una política antigua con tarifa en cero no vende visitas', function () {
    $s = VisitScenario::make(fn ($f) => $f->state(['fee_amount' => '0.00']));

    expect(fn () => $s->book())->toThrow(VisitBookingException::class, 'Por ahora agenda tu visita con un asesor.');
    expect(SiteVisit::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(CalendarBooking::query()->count())->toBe(0);
});

it('una visita acreditable se cobra completa: el crédito abona al proyecto, no a la visita', function () {
    $s = VisitScenario::make(fn ($f) => $f->creditablePercent('100', 60));
    $visit = $s->book();
    $payment = VisitScenario::paymentOf($visit);

    expect($visit->status)->toBe(VisitStatus::PendingPayment)
        ->and($payment->amount)->toBe($visit->fee_amount)
        ->and($payment->amountMoney()->isPositive())->toBeTrue()
        ->and($visit->hold_expires_at)->not->toBeNull();
});

it('lanza VisitBookingException sin política activa', function () {
    VisitScenario::make()->policy->update(['is_active' => false]);

    expect(fn () => app(VisitBookingService::class)->book(VisitScenario::bookingData(), (string) Str::uuid()))
        ->toThrow(VisitBookingException::class, 'Por ahora agenda tu visita con un asesor.');
});

it('no cuenta zonas inactivas como cobertura', function () {
    $s = VisitScenario::make();
    CoverageZone::query()->whereKey($s->zone->id)->update(['is_active' => false]);

    $this->postJson(route('public.visita.cobertura'), ['postal_code' => VisitScenario::POSTAL_CODE])->assertJson(['covered' => false]);
});
