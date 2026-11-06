<?php

use App\Notifications\Visits\VisitScheduledNotification;
use App\Services\Visits\VisitCheckoutUrls;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Visits\VisitScenario;

/*
 * Pantalla final de la visita técnica tras el pago (Openpay simulado):
 * datos completos, calendario (.ics + Google) y correo con el .ics.
 */

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make();
    $this->visit = $this->s->book();
    $this->payment = VisitScenario::paymentOf($this->visit);
});

function payVisit($test): void
{
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trxvisita', 'order_id' => $r['order_id'], 'amount' => (float) $test->payment->amount])),
        'GET get' => Factory::response(VisitScenario::transaction(['id' => 'trxvisita', 'amount' => (float) $test->payment->amount])),
    ]);
    $test->postJson(VisitCheckoutUrls::charge($test->payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);
}

it('antes del pago no hay calendario; después la pantalla trae todo lo necesario', function () {
    $this->get(VisitCheckoutUrls::status($this->payment))
        ->assertInertia(fn (Assert $p) => $p->where('calendar', null));
    $this->get(VisitCheckoutUrls::calendar($this->payment))->assertNotFound();

    payVisit($this);

    $this->get(VisitCheckoutUrls::status($this->payment))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('public/visita/Estado', false)
            ->where('payment.status.value', 'paid')
            ->where('payment.amount', $this->payment->amount)
            ->where('payment.cardLast4', '1111')
            ->where('visit.folio', $this->visit->folio)
            ->where('visit.startsAt', $this->visit->starts_at->toIso8601String())
            ->where('visit.technicianFirstName', 'Técnico1')
            ->where('calendar.googleUrl', fn ($u) => str_starts_with((string) $u, 'https://calendar.google.com/'))
            ->where('calendar.icsUrl', fn ($u) => str_contains((string) $u, '/calendario.ics'))
            ->where('portalUrl', route('portal.citas'))
            ->has('receiptUrl'));
});

it('el .ics de la visita lleva domicilio y horario en UTC', function () {
    payVisit($this);

    $ics = $this->get(VisitCheckoutUrls::calendar($this->payment))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')
        ->getContent();

    $unfolded = str_replace("\r\n ", '', $ics);
    expect($unfolded)->toContain('UID:'.$this->visit->uuid.'@mlhabitat')
        ->toContain('DTSTART:'.$this->visit->starts_at->copy()->utc()->format('Ymd\THis\Z'))
        ->toContain('SUMMARY:Visita técnica ML Habitat')
        ->toContain('LOCATION:');
});

it('el correo de confirmación adjunta el .ics y muestra monto y tarjeta', function () {
    payVisit($this);

    Notification::assertSentOnDemand(VisitScheduledNotification::class, function (VisitScheduledNotification $n) {
        $mail = $n->toMail(new AnonymousNotifiable);
        $text = implode("\n", $mail->introLines);

        return collect($mail->rawAttachments)->contains(fn ($a) => $a['name'] === 'visita-ml-habitat.ics' && str_contains($a['data'], 'BEGIN:VEVENT'))
            && str_contains($text, 'terminación 1111')
            && str_contains($text, 'calendar.google.com');
    });
});

it('pago rechazado: estado fallido con reintento mientras el apartado es válido', function () {
    VisitScenario::fakeOpenpay(['POST charge' => Factory::response(VisitScenario::error(3003), 402)]);
    $this->postJson(VisitCheckoutUrls::charge($this->payment), VisitScenario::chargeBody())->assertJson(['status' => 'failed']);

    $this->get(VisitCheckoutUrls::status($this->payment))
        ->assertInertia(fn (Assert $p) => $p->where('payment.status.value', 'failed')
            ->where('payment.errorMessage', fn ($m) => is_string($m) && $m !== '')
            ->where('payUrl', fn ($u) => str_contains((string) $u, '/agendar-visita/pago/'))
            ->where('calendar', null));
});
