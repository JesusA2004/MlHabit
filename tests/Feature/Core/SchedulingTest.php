<?php

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarBooking;
use App\Models\User;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotFinder;
use App\Services\Scheduling\SlotUnavailableException;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 07:00')); // lunes
    $this->finder = app(SlotFinder::class);
    $this->bookings = app(BookingService::class);
    $this->tech = User::factory()->role(UserRole::Technician)->create();
    AvailabilityRule::query()->create([
        'user_id' => $this->tech->id, 'kind' => BookingKind::Visit, 'weekday' => 2,
        'starts_at' => '09:00:00', 'ends_at' => '14:00:00', 'is_active' => true,
    ]);
    $this->tuesday = CarbonImmutable::parse('2026-11-03');
});

function schedAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-11-03 {$time}");
}

it('finds slots inside the recurring windows', function () {
    $slots = $this->finder->forDay($this->tech, BookingKind::Visit, $this->tuesday, 90, 0, 60);

    expect(collect($slots)->map(fn ($s) => $s['starts_at']->format('H:i'))->all())
        ->toBe(['09:00', '10:00', '11:00', '12:00']);
});

it('ignores rules of another agenda kind and outside effective dates', function () {
    expect($this->finder->forDay($this->tech, BookingKind::Consultation, $this->tuesday, 30))->toBe([]);

    AvailabilityRule::query()->update(['effective_from' => '2026-11-10']);
    expect($this->finder->forDay($this->tech, BookingKind::Visit, $this->tuesday, 30))->toBe([]);
});

it('subtracts blocks and adds extra hours', function () {
    AvailabilityException::query()->create([
        'user_id' => $this->tech->id, 'kind' => null, 'type' => AvailabilityExceptionType::Blocked,
        'starts_at' => schedAt('10:00'), 'ends_at' => schedAt('12:00'),
    ]);
    AvailabilityException::query()->create([
        'user_id' => $this->tech->id, 'kind' => BookingKind::Visit, 'type' => AvailabilityExceptionType::Extra,
        'starts_at' => schedAt('16:00'), 'ends_at' => schedAt('18:00'),
    ]);

    $windows = collect($this->finder->windows($this->tech, BookingKind::Visit, $this->tuesday))
        ->map(fn ($w) => $w[0]->format('H:i').'-'.$w[1]->format('H:i'))->all();

    expect($windows)->toBe(['09:00-10:00', '12:00-14:00', '16:00-18:00']);
});

it('removes the whole day on vacation', function () {
    AvailabilityException::query()->create([
        'user_id' => $this->tech->id, 'type' => AvailabilityExceptionType::Vacation,
        'starts_at' => '2026-11-01 00:00', 'ends_at' => '2026-11-08 00:00',
    ]);

    expect($this->finder->forDay($this->tech, BookingKind::Visit, $this->tuesday, 60))->toBe([]);
});

it('respects existing bookings with buffer and minimum notice', function () {
    $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('10:00'), schedAt('11:30'), 30);

    $slots = collect($this->finder->forDay($this->tech, BookingKind::Visit, $this->tuesday, 60, 30, 30))
        ->map(fn ($s) => $s['starts_at']->format('H:i'))->all();
    expect($slots)->toBe(['12:00', '12:30', '13:00']);

    $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00'));
    $late = collect($this->finder->forDay($this->tech, BookingKind::Visit, $this->tuesday, 60, 0, 30, 2))
        ->map(fn ($s) => $s['starts_at']->format('H:i'))->all();
    expect($late)->toBe(['13:00']);
});

it('reserves a slot only once (serialized and unique slot key)', function () {
    $first = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:30'), 30);

    expect($first->status)->toBe(BookingStatus::Confirmed)
        ->and($first->slot_key)->toBe($this->tech->id.'|2026-11-03 09:00');

    expect(fn () => $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:30'), 30))
        ->toThrow(SlotUnavailableException::class);
    expect(fn () => $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('10:45'), schedAt('12:00'), 30))
        ->toThrow(SlotUnavailableException::class);
    expect(CalendarBooking::query()->count())->toBe(1);
});

it('rejects reservations outside availability unless told not to check', function () {
    expect(fn () => $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('15:00'), schedAt('16:00')))
        ->toThrow(SlotUnavailableException::class);

    $booking = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('15:00'), schedAt('16:00'), checkAvailability: false);
    expect($booking->exists)->toBeTrue();
});

it('expires holds and frees the slot', function () {
    $hold = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:00'), 0, now()->addMinutes(15));
    expect($hold->status)->toBe(BookingStatus::Held);

    $this->travelTo(now()->addMinutes(20));
    $again = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:00'));

    expect($hold->refresh()->status)->toBe(BookingStatus::Released)
        ->and($hold->slot_key)->toBeNull()
        ->and($again->status)->toBe(BookingStatus::Confirmed);
});

it('confirms and releases idempotently', function () {
    $hold = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:00'), 0, now()->addMinutes(15));

    $this->bookings->confirm($hold);
    $this->bookings->confirm($hold);
    expect($hold->refresh()->status)->toBe(BookingStatus::Confirmed)->and($hold->hold_expires_at)->toBeNull();

    $this->bookings->release($hold);
    $this->bookings->release($hold);
    expect($hold->refresh()->status)->toBe(BookingStatus::Released)->and($hold->slot_key)->toBeNull();
});

it('recovers a released hold on late confirmation only if still free', function () {
    $hold = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('09:00'), schedAt('10:00'), 0, now()->addMinutes(15));
    $this->travelTo(now()->addMinutes(20));
    $this->bookings->releaseExpiredHolds();

    $this->bookings->confirm($hold);
    expect($hold->refresh()->status)->toBe(BookingStatus::Confirmed);

    $other = $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('11:00'), schedAt('12:00'), 0, now()->addMinutes(15));
    $this->travelTo(now()->addMinutes(20));
    $this->bookings->reserve($this->tech, BookingKind::Visit, schedAt('11:00'), schedAt('12:00'));

    expect(fn () => $this->bookings->confirm($other))->toThrow(SlotUnavailableException::class);
});
