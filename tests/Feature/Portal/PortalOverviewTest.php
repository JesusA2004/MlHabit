<?php

use App\Enums\ConsultationChannel;
use App\Enums\LeadStatus;
use App\Enums\PaymentConcept;
use App\Enums\PaymentStatus;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Projects\Support\Fixtures;

beforeEach(function () {
    Notification::fake();
    $this->withoutVite();
});

it('muestra al cliente solo sus solicitudes', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    [, $bobCustomer] = Fixtures::client();
    $mine = Lead::factory()->create(['customer_id' => $aliceCustomer->id, 'status' => LeadStatus::Contacted]);
    Lead::factory()->create(['customer_id' => $bobCustomer->id]);

    $this->actingAs($alice)->get(route('portal.solicitudes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Solicitudes')
            ->has('requests', 1)
            ->where('requests.0.uuid', $mine->uuid)
            ->where('requests.0.status.label', 'Te contactamos')
            ->has('stages', 5));
});

it('incluye solicitudes sin cuenta hechas con el mismo correo verificado', function () {
    [$alice] = Fixtures::client();
    $guest = Customer::factory()->create(['user_id' => null, 'email' => strtoupper($alice->email)]);
    $lead = Lead::factory()->create(['customer_id' => $guest->id]);

    // Una ficha con el mismo correo pero ligada a OTRA cuenta no se mezcla.
    [, $other] = Fixtures::client();
    $other->forceFill(['email' => $alice->email])->save();
    Lead::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($alice)->get(route('portal.solicitudes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.uuid', $lead->uuid));
});

it('no mezcla solicitudes por correo si la cuenta no está verificada', function () {
    [$alice] = Fixtures::client();
    $alice->forceFill(['email_verified_at' => null])->save();
    $guest = Customer::factory()->create(['user_id' => null, 'email' => $alice->email]);
    Lead::factory()->create(['customer_id' => $guest->id]);

    // El portal exige correo verificado; la regla de mezcla tampoco lo usa.
    $this->actingAs($alice)->get(route('portal.solicitudes.index'))->assertRedirect();
});

it('las solicitudes sin sesión o de otro rol no son accesibles', function () {
    $this->get(route('portal.solicitudes.index'))->assertRedirect();
    $this->actingAs(Fixtures::staff())->get(route('portal.solicitudes.index'))->assertForbidden();
    $this->actingAs(Fixtures::technician())->get(route('portal.solicitudes.index'))->assertForbidden();
});

it('lista todos los pagos del cliente con comprobante y su concepto', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    [, $bobCustomer] = Fixtures::client();
    $visit = Fixtures::visit($aliceCustomer, ['status' => 'completed']);
    Fixtures::payment($aliceCustomer, PaymentConcept::VisitFee, '1500.00', PaymentStatus::Paid, ['site_visit_id' => $visit->id, 'paid_at' => now()]);
    Fixtures::payment($aliceCustomer, PaymentConcept::Other, '500.00', PaymentStatus::Paid, ['paid_at' => now()]);
    // Intento abandonado sin forma de pagarse: no ensucia el historial.
    Fixtures::payment($aliceCustomer, PaymentConcept::Other, '999.00', PaymentStatus::Failed);
    Fixtures::payment($bobCustomer, PaymentConcept::VisitFee, '1500.00');

    $this->actingAs($alice)->get(route('portal.pagos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Pagos')
            ->has('payments', 2)
            ->where('totals.paid', '2000.00')
            ->where('payments', fn ($payments) => collect($payments)->every(fn ($p) => str_contains((string) $p['receipt_url'], 'signature=')))
            ->where('payments', fn ($payments) => collect($payments)->contains(fn ($p) => $p['subject']['kind'] === 'visit')));
});

it('muestra las asesorías del cliente con enlace para entrar y para cambiarla', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    [, $bobCustomer] = Fixtures::client();
    $lead = Lead::factory()->create(['customer_id' => $aliceCustomer->id]);
    $start = now()->addDays(3)->setTime(10, 0);
    Consultation::factory()->create([
        'lead_id' => $lead->id,
        'starts_at' => $start,
        'ends_at' => $start->copy()->addMinutes(45),
        'channel' => ConsultationChannel::Video,
        'meeting_url' => 'https://meet.example.com/abc',
    ]);
    Consultation::factory()->create(['lead_id' => Lead::factory()->create(['customer_id' => $bobCustomer->id])->id]);

    $this->actingAs($alice)->get(route('portal.citas'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Citas')
            ->has('upcoming', 1)
            ->where('upcoming.0.is_video', true)
            ->where('upcoming.0.meeting_url', 'https://meet.example.com/abc')
            ->where('upcoming.0.reschedule_url', fn ($url) => str_contains((string) $url, 'signature=')));
});

it('el inicio resume solicitudes, citas y pagos aunque no haya proyecto', function () {
    [$alice, $aliceCustomer] = Fixtures::client();
    Lead::factory()->create(['customer_id' => $aliceCustomer->id]);
    Fixtures::payment($aliceCustomer, PaymentConcept::VisitFee, '1500.00', PaymentStatus::Paid, ['paid_at' => now()]);

    $this->actingAs($alice)->get(route('portal.inicio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Inicio')
            ->where('project', null)
            ->has('requests', 1)
            ->where('stats.open_requests', 1)
            ->where('stats.paid', '1500.00')
            ->has('activity', 2));
});
