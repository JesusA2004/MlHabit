<?php

namespace App\Services\Scheduling;

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula horarios libres de una persona para un día:
 * disponibilidad recurrente + horario extra − bloqueos/vacaciones −
 * reservas activas (con buffer) − anticipación mínima.
 */
class SlotFinder
{
    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function forDay(
        User $resource,
        BookingKind $kind,
        CarbonImmutable $day,
        int $durationMinutes,
        int $bufferMinutes = 0,
        int $stepMinutes = 30,
        int $minNoticeHours = 0,
    ): array {
        $windows = $this->windows($resource, $kind, $day);
        if ($windows === []) {
            return [];
        }

        $dayStart = $day->startOfDay();
        $dayEnd = $day->endOfDay();
        $earliest = CarbonImmutable::now()->addHours($minNoticeHours);

        $busy = CalendarBooking::query()
            ->where('user_id', $resource->getKey())
            ->occupying()
            ->where('starts_at', '<', $dayEnd->addMinutes($bufferMinutes))
            ->where('ends_at', '>', $dayStart->subMinutes($bufferMinutes))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (CalendarBooking $b) => [CarbonImmutable::parse($b->starts_at), CarbonImmutable::parse($b->ends_at)]);

        $slots = [];
        foreach ($windows as [$wStart, $wEnd]) {
            for ($start = $wStart; $start->addMinutes($durationMinutes)->lessThanOrEqualTo($wEnd); $start = $start->addMinutes($stepMinutes)) {
                $end = $start->addMinutes($durationMinutes);

                if ($start->lessThan($earliest)) {
                    continue;
                }

                $from = $start->subMinutes($bufferMinutes);
                $to = $end->addMinutes($bufferMinutes);
                $clash = $busy->contains(fn (array $b) => $b[0]->lessThan($to) && $b[1]->greaterThan($from));

                if (! $clash) {
                    $slots[$start->format('Y-m-d H:i')] = ['starts_at' => $start, 'ends_at' => $end];
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /** ¿El intervalo cae completo dentro de una ventana disponible? */
    public function isWithinAvailability(User $resource, BookingKind $kind, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        if (! $startsAt->isSameDay($endsAt)) {
            return false;
        }

        foreach ($this->windows($resource, $kind, $startsAt) as [$wStart, $wEnd]) {
            if ($startsAt->greaterThanOrEqualTo($wStart) && $endsAt->lessThanOrEqualTo($wEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ventanas libres del día (antes de restar reservas).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function windows(User $resource, BookingKind $kind, CarbonImmutable $day): array
    {
        $date = $day->toDateString();

        /** @var Collection<int, AvailabilityRule> $rules */
        $rules = AvailabilityRule::query()
            ->where('user_id', $resource->getKey())
            ->where('kind', $kind->value)
            ->where('weekday', $day->dayOfWeek)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>=', $date))
            ->get();

        $windows = $rules->map(fn (AvailabilityRule $r) => [
            CarbonImmutable::parse("{$date} {$r->starts_at}"),
            CarbonImmutable::parse("{$date} {$r->ends_at}"),
        ])->all();

        $exceptions = AvailabilityException::query()
            ->where('user_id', $resource->getKey())
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', $kind->value))
            ->where('starts_at', '<', $day->endOfDay())
            ->where('ends_at', '>', $day->startOfDay())
            ->get();

        foreach ($exceptions->filter(fn (AvailabilityException $e) => $e->type === AvailabilityExceptionType::Extra) as $extra) {
            $windows[] = [
                CarbonImmutable::parse($extra->starts_at)->max($day->startOfDay()),
                CarbonImmutable::parse($extra->ends_at)->min($day->endOfDay()),
            ];
        }

        foreach ($exceptions->filter(fn (AvailabilityException $e) => $e->type->removesTime()) as $block) {
            $bStart = CarbonImmutable::parse($block->starts_at);
            $bEnd = CarbonImmutable::parse($block->ends_at);
            $next = [];
            foreach ($windows as [$wStart, $wEnd]) {
                if ($bEnd->lessThanOrEqualTo($wStart) || $bStart->greaterThanOrEqualTo($wEnd)) {
                    $next[] = [$wStart, $wEnd];

                    continue;
                }
                if ($bStart->greaterThan($wStart)) {
                    $next[] = [$wStart, $bStart];
                }
                if ($bEnd->lessThan($wEnd)) {
                    $next[] = [$bEnd, $wEnd];
                }
            }
            $windows = $next;
        }

        usort($windows, fn ($a, $b) => $a[0] <=> $b[0]);

        return $windows;
    }
}
