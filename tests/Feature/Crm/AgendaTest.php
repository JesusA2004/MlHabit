<?php

use App\Enums\BookingKind;
use App\Enums\UserRole;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
    $this->ops = User::factory()->role(UserRole::Operations)->create()->refresh();
    $this->advisor = User::factory()->role(UserRole::Sales)->create()->refresh();
});

it('replaces the weekly rules of an advisor', function () {
    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $this->advisor), [
        'kind' => 'consultation',
        'ranges' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['weekday' => 1, 'starts_at' => '16:00', 'ends_at' => '19:00'],
            ['weekday' => 3, 'starts_at' => '10:00', 'ends_at' => '14:00'],
        ],
        'effective_from' => '2026-11-02',
    ])->assertSessionHasNoErrors();

    expect(AvailabilityRule::query()->where('user_id', $this->advisor->id)->where('kind', 'consultation')->count())->toBe(3);

    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $this->advisor), [
        'kind' => 'consultation',
        'ranges' => [['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '12:00']],
    ])->assertSessionHasNoErrors();

    expect(AvailabilityRule::query()->where('user_id', $this->advisor->id)->pluck('weekday')->all())->toBe([2]);
});

it('rejects ranges where the end is not after the start', function () {
    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $this->advisor), [
        'kind' => 'consultation',
        'ranges' => [['weekday' => 1, 'starts_at' => '13:00', 'ends_at' => '09:00']],
    ])->assertSessionHasErrors('ranges.0.ends_at');
});

it('rejects overlapping ranges on the same weekday', function () {
    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $this->advisor), [
        'kind' => 'consultation',
        'ranges' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
            ['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '15:00'],
        ],
    ])->assertSessionHasErrors('ranges.1.starts_at');

    expect(AvailabilityRule::query()->count())->toBe(0);
});

it('only lets technicians have a visit schedule', function () {
    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $this->advisor), [
        'kind' => 'visit',
        'ranges' => [['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00']],
    ])->assertStatus(422);

    $tech = User::factory()->role(UserRole::Technician)->create()->refresh();
    $this->actingAs($this->ops)->put(route('admin.agenda.horario', $tech), [
        'kind' => 'visit',
        'ranges' => [['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00']],
    ])->assertSessionHasNoErrors();
});

it('creates exceptions and rejects overlapping ones', function () {
    $this->actingAs($this->ops)->post(route('admin.agenda.excepciones.store', $this->advisor), [
        'type' => 'vacation', 'kind' => 'consultation',
        'starts_at' => '2026-12-20 00:00', 'ends_at' => '2026-12-31 23:59', 'reason' => 'Vacaciones',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->ops)->post(route('admin.agenda.excepciones.store', $this->advisor), [
        'type' => 'blocked', 'kind' => 'consultation',
        'starts_at' => '2026-12-24 10:00', 'ends_at' => '2026-12-24 12:00',
    ])->assertSessionHasErrors('starts_at');

    $this->actingAs($this->ops)->post(route('admin.agenda.excepciones.store', $this->advisor), [
        'type' => 'extra', 'kind' => 'consultation',
        'starts_at' => '2026-11-07 10:00', 'ends_at' => '2026-11-07 09:00',
    ])->assertSessionHasErrors('ends_at');

    expect(AvailabilityException::query()->count())->toBe(1);
});

it('renders the agenda with weekly windows and bookings', function () {
    AvailabilityRule::query()->create([
        'user_id' => $this->advisor->id, 'kind' => BookingKind::Consultation, 'weekday' => 1,
        'starts_at' => '09:00:00', 'ends_at' => '13:00:00', 'is_active' => true,
    ]);

    $this->actingAs($this->ops)->get(route('admin.agenda.index', ['tipo' => 'consultation', 'persona' => $this->advisor->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/agenda/Index')
            ->where('person.id', $this->advisor->id)
            ->has('days', 7)
            ->where('days.0.windows.0.start', '09:00'));
});
