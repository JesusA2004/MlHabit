<?php

namespace App\Services\Visits;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\VisitStatus;
use App\Models\CalendarBooking;
use App\Models\CoverageZone;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Services\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Disponibilidad de visitas por zona: agrega los horarios libres de todos
 * los técnicos activos de la zona (SlotFinder con duración, buffer y
 * anticipación de la política) y respeta el cupo diario de la zona.
 * El cliente elige una ventana de horario; el técnico se asigna al reservar.
 */
class VisitAvailability
{
    public const STEP_MINUTES = 30;

    public function __construct(private readonly SlotFinder $slots) {}

    /** @return Collection<int, User> */
    public function technicians(CoverageZone $zone): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('coverageZones', fn ($q) => $q->whereKey($zone->id))
            ->orderBy('id')
            ->get();
    }

    /** Buffer entre visitas: el mayor entre el de la política y el traslado de la zona. */
    public function bufferMinutes(CoverageZone $zone, VisitPolicy $policy): int
    {
        return max($policy->buffer_minutes, (int) $zone->travel_buffer_minutes);
    }

    public function firstBookableDay(VisitPolicy $policy): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours($policy->min_notice_hours)->startOfDay();
    }

    public function lastBookableDay(VisitPolicy $policy): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays($policy->booking_window_days)->endOfDay();
    }

    public function withinWindow(VisitPolicy $policy, CarbonImmutable $startsAt): bool
    {
        return $startsAt->greaterThanOrEqualTo(CarbonImmutable::now()->addHours($policy->min_notice_hours))
            && $startsAt->lessThanOrEqualTo($this->lastBookableDay($policy));
    }

    /** ¿La zona ya llenó su cupo de visitas de ese día? */
    public function zoneCapacityReached(CoverageZone $zone, CarbonImmutable $day, ?int $ignoreVisitId = null): bool
    {
        if (! $zone->max_visits_per_day) {
            return false;
        }

        $count = SiteVisit::query()
            ->where('coverage_zone_id', $zone->id)
            ->whereBetween('starts_at', [$day->startOfDay(), $day->endOfDay()])
            ->whereIn('status', VisitStatus::occupyingValues())
            ->where(function ($q) {
                // Un apartado vencido sin pagar ya no ocupa cupo.
                $q->whereNotIn('status', [VisitStatus::PendingPayment->value, VisitStatus::PaymentProcessing->value])
                    ->orWhere('hold_expires_at', '>', now())
                    ->orWhere('status', VisitStatus::PaymentProcessing->value);
            })
            ->when($ignoreVisitId, fn ($q) => $q->whereKeyNot($ignoreVisitId))
            ->count();

        return $count >= $zone->max_visits_per_day;
    }

    /**
     * Ventanas libres del día agregadas entre técnicos.
     *
     * @return list<array{start: string, end: string, startsAt: string, endsAt: string, technicianIds: list<int>}>
     */
    public function slotsForDay(CoverageZone $zone, VisitPolicy $policy, CarbonImmutable $day, ?int $onlyTechnicianId = null, ?int $ignoreVisitId = null): array
    {
        $day = $day->startOfDay();

        if ($day->lessThan($this->firstBookableDay($policy)) || $day->greaterThan($this->lastBookableDay($policy))) {
            return [];
        }

        if ($this->zoneCapacityReached($zone, $day, $ignoreVisitId)) {
            return [];
        }

        $buffer = $this->bufferMinutes($zone, $policy);
        $aggregated = [];

        foreach ($this->technicians($zone) as $technician) {
            if ($onlyTechnicianId !== null && $technician->id !== $onlyTechnicianId) {
                continue;
            }

            $free = $this->slots->forDay($technician, BookingKind::Visit, $day, $policy->duration_minutes, $buffer, self::STEP_MINUTES, $policy->min_notice_hours);

            foreach ($free as $slot) {
                $key = $slot['starts_at']->format('H:i');
                $aggregated[$key] ??= [
                    'start' => $key,
                    'end' => $slot['ends_at']->format('H:i'),
                    'startsAt' => $slot['starts_at']->toIso8601String(),
                    'endsAt' => $slot['ends_at']->toIso8601String(),
                    'technicianIds' => [],
                ];
                $aggregated[$key]['technicianIds'][] = $technician->id;
            }
        }

        ksort($aggregated);

        return array_values($aggregated);
    }

    /**
     * Fechas (Y-m-d) con al menos un horario libre dentro de la ventana de reserva.
     *
     * @return list<string>
     */
    public function availableDates(CoverageZone $zone, VisitPolicy $policy): array
    {
        if ($this->technicians($zone)->isEmpty()) {
            return [];
        }

        $dates = [];
        $last = $this->lastBookableDay($policy);

        for ($day = $this->firstBookableDay($policy); $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            if ($this->slotsForDay($zone, $policy, $day) !== []) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /**
     * Candidatos para un horario, del menos al más cargado ese día
     * (reservas de visita que ocupan agenda), desempate por id.
     *
     * @param  list<int>  $technicianIds
     * @return list<int>
     */
    public function rankByLoad(array $technicianIds, CarbonImmutable $day): array
    {
        if ($technicianIds === []) {
            return [];
        }

        $loads = CalendarBooking::query()
            ->whereIn('user_id', $technicianIds)
            ->where('kind', BookingKind::Visit->value)
            ->whereBetween('starts_at', [$day->startOfDay(), $day->endOfDay()])
            ->where(function ($q) {
                $q->where('status', BookingStatus::Confirmed->value)
                    ->orWhere(fn ($h) => $h->where('status', BookingStatus::Held->value)->where('hold_expires_at', '>', now()));
            })
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $ids = $technicianIds;
        usort($ids, fn (int $a, int $b) => [(int) ($loads[$a] ?? 0), $a] <=> [(int) ($loads[$b] ?? 0), $b]);

        return $ids;
    }
}
