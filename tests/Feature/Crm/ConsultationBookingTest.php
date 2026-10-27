<?php

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\LeadStatus;
use App\Models\AvailabilityException;
use App\Models\CalendarBooking;
use App\Models\Consultation;
use App\Models\Lead;
use App\Notifications\Crm\ConsultationAdvisorAlert;
use App\Notifications\Crm\ConsultationBooked;
use App\Notifications\Crm\ConsultationReminder;
use App\Services\Crm\ConsultationScheduler;
use App\Services\Scheduling\SlotUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Crm\CrmSetup;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    // Lunes 2 de noviembre de 2026, 08:00 (hora del centro de México).
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
    $this->scheduler = app(ConsultationScheduler::class);
});

it('lists available slots respecting rules, exceptions and minimum notice', function () {
    $advisor = CrmSetup::advisor([1], '09:00', '13:00');

    // Hoy (lunes) con 4 h de anticipación mínima: desde las 12:00.
    $today = collect($this->scheduler->slotsForDate(CarbonImmutable::parse('2026-11-02')))->pluck('time')->all();
    expect($today)->toBe(['12:00', '12:30']);

    // Bloqueo de 10:00 a 11:30 el lunes siguiente.
    AvailabilityException::query()->create([
        'user_id' => $advisor->id, 'kind' => BookingKind::Consultation, 'type' => AvailabilityExceptionType::Blocked,
        'starts_at' => '2026-11-09 10:00', 'ends_at' => '2026-11-09 11:30',
    ]);
    $next = collect($this->scheduler->slotsForDate(CarbonImmutable::parse('2026-11-09')))->pluck('time')->all();
    expect($next)->toBe(['09:00', '09:30', '11:30', '12:00', '12:30']);

    // Martes sin horario.
    expect($this->scheduler->slotsForDate(CarbonImmutable::parse('2026-11-03')))->toBe([]);
});

it('returns only dates with availability within the booking window', function () {
    CrmSetup::advisor([3], '10:00', '12:00'); // miércoles

    $dates = $this->scheduler->availableDates(2026, 11);

    expect($dates)->toBe(['2026-11-04', '2026-11-11', '2026-11-18'])
        ->and($dates)->not->toContain('2026-11-25'); // fuera de la ventana de 21 días
});

it('books a free consultation from the signed public link', function () {
    $advisor = CrmSetup::advisor([2]);
    $lead = Lead::factory()->create();

    $response = $this->post(URL::signedRoute('public.asesoria.store', ['lead' => $lead->uuid]), [
        'starts_at' => '2026-11-03T10:00:00-06:00',
        'channel' => 'whatsapp',
        'notes' => 'Cocina de 3 m',
    ]);

    $consultation = Consultation::query()->sole();
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/asesoria/'.$consultation->uuid);

    expect($consultation->advisor_id)->toBe($advisor->id)
        ->and($consultation->starts_at->format('Y-m-d H:i'))->toBe('2026-11-03 10:00')
        ->and($consultation->ends_at->format('H:i'))->toBe('10:30')
        ->and($consultation->folio)->toStartWith('MLH-A-')
        ->and($consultation->channel)->toBe(ConsultationChannel::Whatsapp)
        ->and($consultation->price)->toBe('0.00')
        ->and($consultation->booking->status)->toBe(BookingStatus::Confirmed)
        ->and($lead->refresh()->status)->toBe(LeadStatus::ConsultationScheduled);

    Notification::assertSentOnDemand(ConsultationBooked::class);
    Notification::assertSentTo($advisor, ConsultationAdvisorAlert::class);
});

it('rejects the booking link without signature', function () {
    $lead = Lead::factory()->create();

    $this->post('/solicitud/'.$lead->uuid.'/asesoria', ['starts_at' => '2026-11-03T10:00:00-06:00', 'channel' => 'video'])
        ->assertForbidden();
});

it('lets only one of two reservations of the same slot succeed', function () {
    CrmSetup::advisor([2]);
    $a = Lead::factory()->create();
    $b = Lead::factory()->create();
    $slot = CarbonImmutable::parse('2026-11-03 10:00');

    $this->scheduler->book($a, $slot, ConsultationChannel::Video);

    expect(fn () => $this->scheduler->book($b, $slot, ConsultationChannel::Video))->toThrow(SlotUnavailableException::class);
    expect(Consultation::query()->count())->toBe(1)
        ->and(CalendarBooking::query()->where('status', 'confirmed')->count())->toBe(1);
});

it('assigns a second advisor when the first one is busy, picking the least loaded', function () {
    $first = CrmSetup::advisor([2]);
    $second = CrmSetup::advisor([2]);
    $slot = CarbonImmutable::parse('2026-11-03 10:00');

    $c1 = $this->scheduler->book(Lead::factory()->create(), $slot, ConsultationChannel::Video);
    $c2 = $this->scheduler->book(Lead::factory()->create(), $slot, ConsultationChannel::Video);

    expect([$c1->advisor_id, $c2->advisor_id])->toEqualCanonicalizing([$first->id, $second->id]);
    expect(fn () => $this->scheduler->book(Lead::factory()->create(), $slot, ConsultationChannel::Video))->toThrow(SlotUnavailableException::class);
});

it('blocks overlapping slots within the buffer', function () {
    CrmSetup::advisor([2]);
    $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 10:00'), ConsultationChannel::Video);

    // 10:30 queda dentro del buffer de 10 minutos de la cita de 10:00–10:30.
    expect(fn () => $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 10:30'), ConsultationChannel::Video))
        ->toThrow(SlotUnavailableException::class);

    $times = collect($this->scheduler->slotsForDate(CarbonImmutable::parse('2026-11-03')))->pluck('time')->all();
    expect($times)->not->toContain('10:00')->not->toContain('10:30')->toContain('11:00');
});

it('rejects slots inside the minimum notice for public bookings', function () {
    CrmSetup::advisor([1]);

    expect(fn () => $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-02 09:00'), ConsultationChannel::Video))
        ->toThrow(SlotUnavailableException::class);
});

it('reschedules releasing the previous booking', function () {
    CrmSetup::advisor([2]);
    $lead = Lead::factory()->create();
    $old = $this->scheduler->book($lead, CarbonImmutable::parse('2026-11-03 10:00'), ConsultationChannel::Video);

    $this->post(URL::signedRoute('public.asesoria.reprogramar', ['consultation' => $old->uuid]), [
        'starts_at' => '2026-11-03T10:30:00-06:00',
    ])->assertRedirect();

    $old->refresh();
    $new = Consultation::query()->where('rescheduled_from_id', $old->id)->sole();

    expect($old->status)->toBe(ConsultationStatus::Rescheduled)
        ->and(CalendarBooking::query()->where('bookable_id', $old->id)->value('status'))->toBe(BookingStatus::Released)
        ->and($new->starts_at->format('H:i'))->toBe('10:30')
        ->and($new->status)->toBe(ConsultationStatus::Scheduled);

    // El horario original vuelve a estar libre.
    $other = $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 11:30'), ConsultationChannel::Video);
    expect($other->exists)->toBeTrue();
});

it('cancels idempotently and frees the slot', function () {
    CrmSetup::advisor([2]);
    $c = $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-03 10:00'), ConsultationChannel::Phone);
    $url = URL::signedRoute('public.asesoria.cancelar', ['consultation' => $c->uuid]);

    $this->post($url, ['reason' => 'Viaje'])->assertRedirect();
    $this->post($url, ['reason' => 'Viaje'])->assertRedirect();

    expect($c->refresh()->status)->toBe(ConsultationStatus::Cancelled)
        ->and(CalendarBooking::query()->where('status', 'released')->count())->toBe(1)
        ->and(collect($this->scheduler->slotsForDate(CarbonImmutable::parse('2026-11-03')))->pluck('time'))->toContain('10:00');
});

it('records the outcome and advances the lead', function () {
    $advisor = CrmSetup::advisor([2]);
    $lead = Lead::factory()->create();
    $c = $this->scheduler->book($lead, CarbonImmutable::parse('2026-11-03 10:00'), ConsultationChannel::Video);

    $this->actingAs($advisor)->post(route('admin.asesorias.resultado', $c), ['outcome' => 'interested_visit', 'notes' => 'Quiere visita'])
        ->assertSessionHasNoErrors();

    expect($c->refresh()->status)->toBe(ConsultationStatus::Completed)
        ->and($lead->refresh()->status)->toBe(LeadStatus::VisitPending);
});

it('lets staff book from the lead detail ignoring minimum notice', function () {
    $advisor = CrmSetup::advisor([1]);
    $lead = Lead::factory()->create();

    $this->actingAs($advisor)->post(route('admin.prospectos.asesorias.store', $lead), [
        'starts_at' => '2026-11-02 09:00',
        'channel' => 'phone',
        'advisor_id' => $advisor->id,
    ])->assertSessionHasNoErrors();

    expect(Consultation::query()->sole()->starts_at->format('H:i'))->toBe('09:00');
});

it('sends 24h and 1h reminders exactly once', function () {
    CrmSetup::advisor([3]);
    $c = $this->scheduler->book(Lead::factory()->create(), CarbonImmutable::parse('2026-11-04 10:00'), ConsultationChannel::Video);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:30'));
    $this->artisan('consultations:send-reminders')->assertSuccessful();
    $this->artisan('consultations:send-reminders')->assertSuccessful();
    Notification::assertSentOnDemandTimes(ConsultationReminder::class, 1);

    $this->travelTo(CarbonImmutable::parse('2026-11-04 09:10'));
    $this->artisan('consultations:send-reminders')->assertSuccessful();
    $this->artisan('consultations:send-reminders')->assertSuccessful();
    Notification::assertSentOnDemandTimes(ConsultationReminder::class, 2);
    expect($c->refresh()->reminder_sent_at)->not->toBeNull();
});

it('serves the public availability endpoint', function () {
    CrmSetup::advisor([2]);

    $this->getJson('/asesorias/disponibilidad?mes=2026-11')->assertOk()->assertJsonFragment(['2026-11-03']);
    $this->getJson('/asesorias/disponibilidad?fecha=2026-11-03')->assertOk()->assertJsonPath('slots.0.time', '09:00');
});
