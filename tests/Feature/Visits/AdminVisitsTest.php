<?php

use App\Enums\BookingStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\CalendarBooking;
use App\Models\Lead;
use App\Models\Refund;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Notifications\Visits\VisitPaymentLinkNotification;
use App\Notifications\Visits\VisitReminderNotification;
use App\Services\Visits\VisitBookingException;
use App\Services\Visits\VisitCheckoutUrls;
use App\Services\Visits\VisitWorkflow;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Visits\VisitScenario;

function visitsStaff(UserRole $role): User
{
    return User::factory()->role($role)->create()->fresh();
}

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->s = VisitScenario::make(fn ($f) => $f->creditablePercent('100', 60));
});

/** Visita pagada con tarjeta (Openpay simulado). */
function paidVisit($test, array $overrides = []): SiteVisit
{
    $visit = $test->s->book($overrides);
    $payment = VisitScenario::paymentOf($visit);
    VisitScenario::fakeOpenpay([
        'POST charge' => fn (Request $r) => Factory::response(VisitScenario::transaction(['id' => 'trx'.Str::random(10), 'order_id' => $r['order_id']])),
    ]);
    $test->postJson(VisitCheckoutUrls::charge($payment), VisitScenario::chargeBody())->assertJson(['status' => 'paid']);

    return $visit->fresh();
}

it('lista visitas por pestaña con permisos', function () {
    paidVisit($this);
    $this->s->book(['email' => 'pend@example.com', 'phone' => '5500001111', 'start' => '14:00']);

    $this->actingAs(visitsStaff(UserRole::Sales))
        ->get(route('admin.visitas.index', ['vista' => 'proximas']))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/visitas/Index', false)
            ->has('visits.data', 1)
            ->where('counts.pendientes', 1)
            ->where('counts.proximas', 1));

    $this->actingAs(visitsStaff(UserRole::Technician))->get(route('admin.visitas.index'))->assertForbidden();
    $this->actingAs(visitsStaff(UserRole::Quality))->get(route('admin.visitas.index'))->assertForbidden();
});

it('el personal crea una visita desde un prospecto y envía el enlace de pago', function () {
    if (! class_exists(Lead::class)) {
        $this->markTestSkipped('Módulo crm no disponible.');
    }

    $lead = Lead::factory()->create(['postal_code' => VisitScenario::POSTAL_CODE, 'status' => LeadStatus::Contacted]);
    $sales = visitsStaff(UserRole::Sales);

    $this->actingAs($sales)
        ->get(route('admin.visitas.create', ['prospecto' => $lead->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/visitas/Crear', false)->where('prefill.lead', $lead->uuid));

    $data = VisitScenario::bookingData(['lead' => $lead->uuid, 'payment_action' => 'link']);
    unset($data['terms']);

    $this->actingAs($sales)->post(route('admin.visitas.store'), $data)->assertRedirect();

    $visit = SiteVisit::query()->sole();
    expect($visit->lead_id)->toBe($lead->id)
        ->and($visit->created_by)->toBe($sales->id)
        ->and($visit->status)->toBe(VisitStatus::PendingPayment)
        ->and($visit->hold_expires_at->greaterThan(now()->addHours(23)))->toBeTrue();

    Notification::assertSentOnDemand(VisitPaymentLinkNotification::class);
});

it('el personal con payments.record agenda con pago manual; comercial no puede', function () {
    $data = VisitScenario::bookingData(['payment_action' => 'manual', 'method' => 'cash', 'reference' => 'Recibo 991']);
    unset($data['terms']);

    $this->actingAs(visitsStaff(UserRole::Sales))->post(route('admin.visitas.store'), $data)->assertSessionHasErrors('payment_action');
    expect(SiteVisit::query()->count())->toBe(0);

    $this->actingAs(visitsStaff(UserRole::SuperAdmin))->post(route('admin.visitas.store'), $data)->assertRedirect();

    $visit = SiteVisit::query()->sole();
    expect($visit->status)->toBe(VisitStatus::Scheduled)
        ->and(VisitScenario::paymentOf($visit)->status)->toBe(PaymentStatus::Paid)
        ->and(CalendarBooking::query()->sole()->status)->toBe(BookingStatus::Confirmed);
});

it('reprograma a un horario libre liberando el anterior de forma atómica', function () {
    $visit = paidVisit($this);
    $ops = visitsStaff(UserRole::Operations);

    $this->actingAs($ops)
        ->post(route('admin.visitas.reprogramar', $visit), ['date' => VisitScenario::day(3), 'start' => '12:00'])
        ->assertSessionHas('success');

    $visit->refresh();
    expect($visit->starts_at->format('Y-m-d H:i'))->toBe(VisitScenario::day(3).' 12:00')
        ->and($visit->status)->toBe(VisitStatus::Scheduled);

    $bookings = CalendarBooking::query()->orderBy('id')->get();
    expect($bookings)->toHaveCount(2)
        ->and($bookings[0]->status)->toBe(BookingStatus::Released)
        ->and($bookings[1]->status)->toBe(BookingStatus::Confirmed);

    // Un horario ocupado no se puede tomar: nada cambia.
    $other = paidVisit($this, ['email' => 'otro@example.com', 'phone' => '5500002222', 'start' => '15:00']);
    $this->actingAs($ops)
        ->post(route('admin.visitas.reprogramar', $other), ['date' => VisitScenario::day(3), 'start' => '12:30'])
        ->assertSessionHasErrors('start');
    expect($other->fresh()->starts_at->format('H:i'))->toBe('15:00')
        ->and($other->activeBooking()->status)->toBe(BookingStatus::Confirmed);
});

it('reasigna técnico re-reservando el horario (visits.assign)', function () {
    $second = User::factory()->role(UserRole::Technician)->create();
    $this->s->zone->technicians()->attach($second->id);
    $visit = paidVisit($this);

    $this->actingAs(visitsStaff(UserRole::Sales))
        ->post(route('admin.visitas.asignar', $visit), ['technician_id' => $second->id])
        ->assertForbidden();

    $this->actingAs(visitsStaff(UserRole::Operations))
        ->post(route('admin.visitas.asignar', $visit), ['technician_id' => $second->id])
        ->assertSessionHas('success');

    expect($visit->fresh()->technician_id)->toBe($second->id)
        ->and($visit->activeBooking()->user_id)->toBe($second->id)
        ->and($visit->activeBooking()->status)->toBe(BookingStatus::Confirmed);
});

it('cancela con reembolso total: libera horario, reembolsa y anula el crédito', function () {
    $visit = paidVisit($this);
    VisitScenario::fakeOpenpay([
        'POST refund' => fn (Request $r) => Factory::response(VisitScenario::transaction(['refund' => ['id' => 'trxrf', 'amount' => $r['amount']]])),
    ]);

    $this->actingAs(visitsStaff(UserRole::SuperAdmin))
        ->post(route('admin.visitas.cancelar', $visit), [
            'reason' => 'El cliente pidió cancelar',
            'refund' => true,
            'refund_amount' => '1000.00',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHas('success');

    $visit->refresh();
    expect($visit->status)->toBe(VisitStatus::Cancelled)
        ->and(CalendarBooking::query()->sole()->status)->toBe(BookingStatus::Released)
        ->and(VisitScenario::paymentOf($visit)->status)->toBe(PaymentStatus::Refunded)
        ->and(Refund::query()->sole()->status)->toBe(RefundStatus::Completed)
        ->and($visit->credit->status->value)->toBe('voided');
});

it('cancelar con reembolso exige payments.refund', function () {
    $visit = paidVisit($this);

    $this->actingAs(visitsStaff(UserRole::Sales))
        ->post(route('admin.visitas.cancelar', $visit), [
            'reason' => 'Prueba sin permiso',
            'refund' => true,
            'refund_amount' => '1000.00',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();

    expect(Refund::query()->count())->toBe(0);
});

it('marca cliente ausente y bloquea transiciones inválidas', function () {
    $visit = paidVisit($this);

    $this->actingAs(visitsStaff(UserRole::Operations))
        ->post(route('admin.visitas.ausente', $visit), ['reason' => 'Nadie abrió'])
        ->assertSessionHas('success');

    expect($visit->fresh()->status)->toBe(VisitStatus::NoShow)
        ->and($visit->fresh()->internal_notes)->toContain('Nadie abrió');

    $this->actingAs(visitsStaff(UserRole::Operations))
        ->post(route('admin.visitas.confirmar', $visit))
        ->assertSessionHas('error');
});

it('VisitWorkflow (contrato con projects): iniciar y terminar son idempotentes y avanzan el prospecto', function () {
    $visit = paidVisit($this);
    $tech = $this->s->technician;
    $workflow = app(VisitWorkflow::class);

    $workflow->start($visit, $tech);
    $workflow->start($visit, $tech);
    expect($visit->fresh()->status)->toBe(VisitStatus::InProgress)
        ->and($visit->fresh()->started_at)->not->toBeNull();

    $workflow->complete($visit, $tech);
    $workflow->complete($visit, $tech);
    $workflow->start($visit, $tech);
    expect($visit->fresh()->status)->toBe(VisitStatus::Completed);

    if (class_exists(Lead::class) && $visit->lead_id) {
        expect(Lead::query()->find($visit->lead_id)->status)->toBe(LeadStatus::VisitDone);
    }

    expect(fn () => $workflow->markNoShow($visit->fresh(), $tech))->toThrow(VisitBookingException::class);
});

it('el pago confirmado mueve el prospecto a visita pagada', function () {
    if (! class_exists(Lead::class)) {
        $this->markTestSkipped('Módulo crm no disponible.');
    }

    $visit = paidVisit($this);
    expect(Lead::query()->find($visit->lead_id)?->status)->toBe(LeadStatus::VisitPaid);
});

it('recordatorios 24 h antes: se envían una sola vez', function () {
    $this->travelTo(now()->startOfDay()->setTime(9, 0));
    $far = paidVisit($this, ['date' => VisitScenario::day(3), 'start' => '10:00', 'email' => 'lejos@example.com', 'phone' => '5500003333']);
    $visit = paidVisit($this, ['date' => VisitScenario::day(1), 'start' => '08:00']);

    $this->artisan('visits:send-reminders')->assertSuccessful();
    $this->artisan('visits:send-reminders')->assertSuccessful();

    expect($visit->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($far->fresh()->reminder_sent_at)->toBeNull();
    Notification::assertSentOnDemandTimes(VisitReminderNotification::class, 1);
    Notification::assertSentToTimes($this->s->technician, VisitReminderNotification::class, 1);
});

it('el detalle de la visita muestra pago, crédito y mapa', function () {
    $visit = paidVisit($this);

    $this->actingAs(visitsStaff(UserRole::Sales))
        ->get(route('admin.visitas.show', $visit))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/visitas/Show', false)
            ->where('visit.folio', $visit->folio)
            ->where('payment.status.value', 'paid')
            ->where('credit.status.value', 'available')
            ->where('visit.address.mapUrl', fn ($url) => str_starts_with($url, 'https://www.google.com/maps/search/?api=1&query='))
            ->where('can.refund', false));
});

it('política de visita: crear versión y activar deja solo una activa', function () {
    $director = visitsStaff(UserRole::Direction);

    $payload = [
        'fee_amount' => '1500.00',
        'is_creditable' => true,
        'credit_type' => 'percent',
        'credit_value' => '100',
        'credit_validity_days' => 90,
        'credit_conditions' => 'Aplica al anticipo del proyecto.',
        'duration_minutes' => 120,
        'buffer_minutes' => 30,
        'hold_minutes' => 20,
        'min_notice_hours' => 24,
        'booking_window_days' => 30,
        'scope' => 'Medición completa del espacio y revisión técnica.',
        'cancellation_policy' => 'Cancelación sin costo con 24 h de anticipación.',
        'reschedule_policy' => 'Una reprogramación sin costo con 24 h de anticipación.',
    ];

    $this->actingAs($director)->post(route('admin.visitas.politica.store'), $payload)->assertSessionHas('success');

    $new = VisitPolicy::query()->orderByDesc('version')->first();
    expect($new->is_active)->toBeFalse()
        ->and($new->version)->toBe($this->s->policy->version + 1);

    $this->actingAs($director)->post(route('admin.visitas.politica.activar', $new))->assertSessionHas('success');

    expect(VisitPolicy::query()->where('is_active', true)->pluck('id')->all())->toBe([$new->id])
        ->and($this->s->policy->fresh()->fee_amount)->toBe('1000.00');

    // Comercial (sin settings.manage ni dirección) no puede.
    $this->actingAs(visitsStaff(UserRole::Sales))->post(route('admin.visitas.politica.store'), $payload)->assertForbidden();
    $this->actingAs(visitsStaff(UserRole::Sales))->post(route('admin.visitas.politica.activar', $this->s->policy))->assertForbidden();

    // Porcentaje > 100 no es válido.
    $this->actingAs($director)->post(route('admin.visitas.politica.store'), ['credit_value' => '150'] + $payload)->assertSessionHasErrors('credit_value');
});
