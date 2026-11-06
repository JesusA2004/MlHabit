<?php

use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Crm\ConsultationBooked;
use App\Services\Crm\ConsultationCheckoutUrls;
use App\Services\Crm\ConsultationHoldExpirer;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Crm\Meetings\JitsiMeetingProvider;
use App\Support\Calendar\CalendarEvent;
use App\Support\Crm\CrmLinks;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Crm\CrmSetup;
use Tests\Feature\Visits\VisitScenario;

/*
 * El precio que el dueño define en Configuración es exactamente lo que se
 * cobra; la videollamada (Jitsi) se crea al confirmarse el pago y solo
 * entonces la ve el cliente; la pantalla final trae calendario y comprobante.
 * Openpay simulado con Http::fake.
 */

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    VisitScenario::configureOpenpay();
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
    $this->advisor = CrmSetup::advisor([2]);
    $this->owner = User::factory()->role(UserRole::SuperAdmin)->create()->fresh();
});

/** @return array<string, mixed> */
function consultationSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'enabled' => true,
        'duration_minutes' => 30,
        'buffer_minutes' => 10,
        'price' => '1,250.50',
        'booking_window_days' => 21,
        'min_notice_hours' => 4,
        'channels' => ['video', 'phone'],
        'meeting_provider' => 'jitsi',
    ], $overrides);
}

function bookVideoConsultation(Lead $lead, string $at = '2026-11-03T10:00:00-06:00'): Consultation
{
    test()->post(URL::signedRoute('public.asesoria.store', ['lead' => $lead->uuid]), [
        'starts_at' => $at,
        'channel' => 'video',
    ])->assertSessionHasNoErrors()->assertRedirect();

    return Consultation::query()->where('lead_id', $lead->id)->latest('id')->firstOrFail();
}

it('el precio del panel llega igual a la reserva, la intención, el checkout, Openpay, el comprobante y el correo', function () {
    $this->actingAs($this->owner)
        ->put('/admin/configuracion/consultation', consultationSettingsPayload())
        ->assertSessionHasNoErrors();
    auth()->logout();

    expect(Settings::get('consultation.price'))->toBe('1250.50');

    $lead = Lead::factory()->create();

    // Página pública de reserva.
    $this->get(URL::signedRoute('public.asesoria.create', ['lead' => $lead->uuid]))
        ->assertInertia(fn (Assert $p) => $p->where('settings.price', '1250.50'));

    $consultation = bookVideoConsultation($lead);
    $payment = Payment::query()->findOrFail($consultation->payment_id);

    expect($consultation->price)->toBe('1250.50')
        ->and($payment->amount)->toBe('1250.50')
        ->and($payment->currency)->toBe('MXN');

    // Pantalla de pago.
    $this->get(ConsultationCheckoutUrls::pay($consultation))
        ->assertInertia(fn (Assert $p) => $p->component('public/asesoria/Pago', false)
            ->where('payment.amount', '1250.50')
            ->where('consultation.price', '1250.50'));

    // Lo que se envía a Openpay: el monto exacto, sin flotantes.
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxprecio', 'order_id' => $r['order_id'], 'amount' => 1250.50])),
        'GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxprecio', 'amount' => 1250.50])),
    ]);
    $this->postJson(ConsultationCheckoutUrls::charge($consultation), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->body(), '"amount":1250.50'));

    // Comprobante.
    $this->get(ConsultationCheckoutUrls::receipt($payment))
        ->assertInertia(fn (Assert $p) => $p->component('public/pagos/Comprobante', false)
            ->where('receipt.amount', '1250.50')
            ->where('receipt.currency', 'MXN')
            ->where('receipt.consultation.folio', $consultation->folio));

    // Correo de confirmación.
    Notification::assertSentOnDemand(ConsultationBooked::class, function (ConsultationBooked $n) {
        $mail = $n->toMail(new AnonymousNotifiable);

        return str_contains(implode("\n", $mail->introLines), '$1,250.50 MXN')
            && collect($mail->rawAttachments)->contains(fn ($a) => $a['name'] === 'asesoria-ml-habitat.ics');
    });
});

it('cambiar el precio no altera las intenciones pendientes ya creadas', function () {
    Settings::put('consultation', ['price' => '600.00']);
    $consultation = bookVideoConsultation(Lead::factory()->create());

    $this->actingAs($this->owner)
        ->put('/admin/configuracion/consultation', consultationSettingsPayload(['price' => '999.00']))
        ->assertSessionHasNoErrors();
    auth()->logout();

    $payment = Payment::query()->findOrFail($consultation->payment_id);
    expect($payment->amount)->toBe('600.00');

    $this->get(ConsultationCheckoutUrls::pay($consultation))
        ->assertInertia(fn (Assert $p) => $p->where('payment.amount', '600.00'));

    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxviejo', 'order_id' => $r['order_id'], 'amount' => 600.00])),
    ]);
    $this->postJson(ConsultationCheckoutUrls::charge($consultation), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->body(), '"amount":600.00'));

    // Una reserva nueva ya usa el precio nuevo.
    $new = bookVideoConsultation(Lead::factory()->create(), '2026-11-03T11:00:00-06:00');
    expect(Payment::query()->findOrFail($new->payment_id)->amount)->toBe('999.00');
});

it('con Jitsi automático el enlace se crea al pagar y antes no se muestra', function () {
    $consultation = bookVideoConsultation(Lead::factory()->create());

    expect($consultation->meeting_url)->toBeNull()
        ->and($consultation->meeting_provider)->toBe('jitsi');

    $this->get(CrmLinks::manageConsultation($consultation))
        ->assertInertia(fn (Assert $p) => $p->where('consultation.meetingUrl', null)->where('calendar', null));

    // Aunque el asesor capture uno antes del pago, el cliente aún no lo ve.
    $consultation->forceFill(['meeting_url' => 'https://meet.example.com/temprano'])->save();
    $this->get(CrmLinks::manageConsultation($consultation))
        ->assertInertia(fn (Assert $p) => $p->where('consultation.meetingUrl', null));
    $this->get(URL::signedRoute('public.asesoria.calendario', ['consultation' => $consultation->uuid]))->assertNotFound();
    $consultation->forceFill(['meeting_url' => null])->save();

    $paid = CrmSetup::pay($consultation);

    expect($paid->status)->toBe(ConsultationStatus::Confirmed)
        ->and($paid->meeting_url)->toMatch('#^https://meet\.jit\.si/MLHabitat-[A-Za-z0-9]{24}$#');

    $other = CrmSetup::pay(bookVideoConsultation(Lead::factory()->create(), '2026-11-03T11:00:00-06:00'));
    expect($other->meeting_url)->not->toBe($paid->meeting_url);

    $this->get(CrmLinks::manageConsultation($paid))
        ->assertInertia(fn (Assert $p) => $p->component('public/asesoria/Show', false)
            ->where('consultation.meetingUrl', $paid->meeting_url)
            ->where('consultation.joinOpensAt', $paid->starts_at->copy()->subMinutes(15)->toIso8601String())
            ->where('payment.amount', '500.00')
            ->where('payment.cardLast4', null)
            ->where('calendar.googleUrl', fn ($url) => str_starts_with((string) $url, 'https://calendar.google.com/calendar/render?action=TEMPLATE'))
            ->where('calendar.icsUrl', fn ($url) => str_contains((string) $url, '/calendario.ics'))
            ->where('portalUrl', route('portal.citas'))
            ->has('receiptUrl'));
});

it('en modo manual no se genera enlace y el asesor puede capturarlo o generar uno de Jitsi', function () {
    Settings::put('consultation', ['meeting_provider' => 'manual']);
    $consultation = CrmSetup::pay(bookVideoConsultation(Lead::factory()->create()));

    expect($consultation->meeting_url)->toBeNull()
        ->and($consultation->meeting_provider)->toBe('manual');

    $this->actingAs($this->advisor)
        ->patch(route('admin.asesorias.update', $consultation), ['meeting_url' => 'https://zoom.us/j/123'])
        ->assertSessionHasNoErrors();
    expect($consultation->fresh()->meeting_url)->toBe('https://zoom.us/j/123');

    $this->actingAs($this->advisor)
        ->post(route('admin.asesorias.videollamada', $consultation))
        ->assertSessionHas('success');

    $fresh = $consultation->fresh();
    expect(JitsiMeetingProvider::isJitsiUrl($fresh->meeting_url))->toBeTrue()
        ->and($fresh->meeting_provider)->toBe('jitsi');
});

it('el panel de configuración guarda la opción de videollamadas', function () {
    $this->actingAs($this->owner)
        ->get('/admin/configuracion')
        ->assertInertia(fn (Assert $p) => $p->where('settings.consultation.meeting_provider', 'jitsi')->has('meetingProviders', 2));

    $this->actingAs($this->owner)
        ->put('/admin/configuracion/consultation', consultationSettingsPayload(['meeting_provider' => 'manual']))
        ->assertSessionHasNoErrors();
    expect(Settings::get('consultation.meeting_provider'))->toBe('manual');

    $this->actingAs($this->owner)
        ->put('/admin/configuracion/consultation', consultationSettingsPayload(['meeting_provider' => 'zoom']))
        ->assertSessionHasErrors('meeting_provider');
});

it('descarga el .ics de una asesoría pagada con fecha en UTC y el enlace de la videollamada', function () {
    $consultation = CrmSetup::pay(bookVideoConsultation(Lead::factory()->create()));

    $response = $this->get(URL::signedRoute('public.asesoria.calendario', ['consultation' => $consultation->uuid]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

    $ics = $response->getContent();
    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->toContain('DTSTART:20261103T160000Z') // 10:00 hora del centro = 16:00 UTC
        ->toContain('DTEND:20261103T163000Z')
        ->toContain('UID:'.$consultation->uuid.'@mlhabitat')
        ->toContain('BEGIN:VALARM')
        ->and(str_replace("\r\n ", '', $ics))->toContain('URL:'.$consultation->meeting_url);

    // Sin firma no hay archivo.
    $this->get(route('public.asesoria.calendario', ['consultation' => $consultation->uuid]))->assertForbidden();
});

it('el enlace de Google Calendar lleva fechas y zona del centro de México', function () {
    $consultation = CrmSetup::pay(bookVideoConsultation(Lead::factory()->create()));
    $url = CalendarEvent::forConsultation($consultation, 'https://x.test/a')->googleCalendarUrl();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['dates'])->toBe('20261103T160000Z/20261103T163000Z')
        ->and($query['ctz'])->toBe('America/Mexico_City')
        ->and($query['details'])->toContain($consultation->meeting_url);
});

it('pago rechazado: mensaje seguro y reintento mientras el apartado sigue vigente; vencido: pantalla de apartado vencido', function () {
    $consultation = bookVideoConsultation(Lead::factory()->create());

    VisitScenario::fakeOpenpay([
        'POST charge' => Factory::response(VisitScenario::error(3001), 402),
    ]);
    $this->postJson(ConsultationCheckoutUrls::charge($consultation), VisitScenario::chargeBody())->assertJson(['status' => 'failed']);

    $this->get(CrmLinks::manageConsultation($consultation))
        ->assertInertia(fn (Assert $p) => $p->where('payment.status.value', 'failed')
            ->where('payment.errorMessage', fn ($m) => is_string($m) && $m !== '' && ! str_contains($m, 'declined'))
            ->where('payUrl', fn ($u) => str_contains((string) $u, '/pago'))
            ->where('consultation.holdExpired', false));

    $this->travel(20)->minutes();
    app(ConsultationHoldExpirer::class)->expire($consultation->fresh());

    $this->get(CrmLinks::manageConsultation($consultation))
        ->assertInertia(fn (Assert $p) => $p->where('consultation.holdExpired', true)
            ->where('payUrl', null)
            ->where('calendar', null)
            ->has('bookAgainUrl'));
});

it('el scheduler solo genera Jitsi para videollamadas', function () {
    $c = app(ConsultationScheduler::class)->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 12:00'), ConsultationChannel::Phone);
    $c = CrmSetup::pay($c);

    expect($c->meeting_url)->toBeNull()->and($c->meeting_provider)->toBeNull();
});
