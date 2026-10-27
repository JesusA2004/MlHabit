<?php

namespace App\Services\Crm;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\ConsultationChannel;
use App\Enums\ConsultationOutcome;
use App\Enums\ConsultationStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\Permission;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarBooking;
use App\Models\Consultation;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Crm\ConsultationAdvisorAlert;
use App\Notifications\Crm\ConsultationBooked;
use App\Notifications\Crm\ConsultationCancelled;
use App\Notifications\Crm\CustomerNotifier;
use App\Notifications\Crm\When;
use App\Services\ActivityLogger;
use App\Services\Crm\Meetings\MeetingProvider;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotFinder;
use App\Services\Scheduling\SlotUnavailableException;
use App\Support\Folio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Agenda de asesorías remotas: disponibilidad agregada entre asesores,
 * reserva atómica (BookingService), reprogramación y cancelación.
 *
 * El cliente no elige asesor: se asigna automáticamente el menos cargado
 * del día entre quienes tienen libre el horario elegido.
 */
class ConsultationScheduler
{
    public const STAFF_WINDOW_DAYS = 60;

    public function __construct(
        private readonly SlotFinder $slots,
        private readonly BookingService $bookings,
        private readonly LeadWorkflow $workflow,
        private readonly MeetingProvider $meetings,
        private readonly ActivityLogger $logger,
    ) {}

    public function settings(): ConsultationSettings
    {
        return ConsultationSettings::current();
    }

    /**
     * Asesores activos con agenda de asesorías configurada.
     *
     * @return Collection<int, User>
     */
    public function advisors(?int $onlyId = null): Collection
    {
        $withSchedule = AvailabilityRule::query()
            ->where('kind', BookingKind::Consultation->value)
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id')
            ->merge(AvailabilityException::query()
                ->where('kind', BookingKind::Consultation->value)
                ->where('ends_at', '>', now())
                ->distinct()
                ->pluck('user_id'))
            ->unique();

        return User::query()
            ->withPermission(Permission::ConsultationsManage)
            ->whereIn('id', $withSchedule)
            ->when($onlyId, fn ($q) => $q->whereKey($onlyId))
            ->orderBy('id')
            ->get();
    }

    /**
     * Primer y último día reservables (zona horaria de la app). El equipo
     * puede agendar hasta STAFF_WINDOW_DAYS aunque la ventana pública sea menor.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(bool $staff = false): array
    {
        $today = CarbonImmutable::today();
        $days = $this->settings()->bookingWindowDays;

        return [$today, $today->addDays($staff ? max($days, self::STAFF_WINDOW_DAYS) : $days)];
    }

    /**
     * Fechas (Y-m-d) con al menos un horario libre en el mes indicado.
     *
     * @return list<string>
     */
    public function availableDates(int $year, int $month, ?int $advisorId = null, ?int $minNoticeHours = null): array
    {
        $settings = $this->settings();
        [$from, $until] = $this->window($minNoticeHours === 0);
        $advisors = $this->advisors($advisorId);

        if ($advisors->isEmpty()) {
            return [];
        }

        $cursor = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $cursor->endOfMonth();
        $dates = [];

        for ($day = $cursor; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if ($day->lessThan($from) || $day->greaterThan($until)) {
                continue;
            }

            foreach ($advisors as $advisor) {
                if ($this->slotsForAdvisor($advisor, $day, $settings, $minNoticeHours) !== []) {
                    $dates[] = $day->toDateString();

                    break;
                }
            }
        }

        return $dates;
    }

    /**
     * Horarios libres de un día, agregados entre asesores.
     *
     * @return list<array{starts_at: string, ends_at: string, time: string, advisors: int}>
     */
    public function slotsForDate(CarbonImmutable $day, ?int $advisorId = null, ?int $minNoticeHours = null): array
    {
        $settings = $this->settings();
        [$from, $until] = $this->window($minNoticeHours === 0);
        if ($day->startOfDay()->lessThan($from) || $day->startOfDay()->greaterThan($until)) {
            return [];
        }

        $merged = [];
        foreach ($this->advisors($advisorId) as $advisor) {
            foreach ($this->slotsForAdvisor($advisor, $day, $settings, $minNoticeHours) as $slot) {
                $key = $slot['starts_at']->format('H:i');
                $merged[$key] ??= ['starts_at' => $slot['starts_at']->toIso8601String(), 'ends_at' => $slot['ends_at']->toIso8601String(), 'time' => $key, 'advisors' => 0];
                $merged[$key]['advisors']++;
            }
        }

        ksort($merged);

        return array_values($merged);
    }

    /**
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function slotsForAdvisor(User $advisor, CarbonImmutable $day, ConsultationSettings $settings, ?int $minNoticeHours): array
    {
        return $this->slots->forDay(
            $advisor,
            BookingKind::Consultation,
            $day->startOfDay(),
            $settings->durationMinutes,
            $settings->bufferMinutes,
            $settings->stepMinutes,
            $minNoticeHours ?? $settings->minNoticeHours,
        );
    }

    /**
     * Agenda una asesoría. Lanza SlotUnavailableException si nadie tiene
     * libre ese horario (p. ej. otra persona lo tomó un segundo antes).
     *
     * @param  bool  $staffBooking  Desde el panel: ignora la anticipación mínima.
     */
    public function book(
        Lead $lead,
        CarbonImmutable $startsAt,
        ConsultationChannel $channel,
        ?string $customerNotes = null,
        ?User $actor = null,
        ?int $advisorId = null,
        bool $staffBooking = false,
        ?Consultation $rescheduledFrom = null,
    ): Consultation {
        $settings = $this->settings();

        if (! $staffBooking && ! $settings->enabled) {
            throw ValidationException::withMessages(['starts_at' => 'Por ahora no estamos agendando asesorías en línea. Te contactaremos para coordinar una fecha.']);
        }
        if (! in_array($channel, $settings->channels, true)) {
            throw ValidationException::withMessages(['channel' => 'Elige uno de los medios disponibles.']);
        }

        $minNotice = $staffBooking ? 0 : $settings->minNoticeHours;
        [, $until] = $this->window($staffBooking);
        if ($startsAt->lessThan(CarbonImmutable::now()->addHours($minNotice)) || $startsAt->startOfDay()->greaterThan($until)) {
            throw new SlotUnavailableException('Ese horario ya no está disponible. Elige otro, por favor.');
        }

        $endsAt = $startsAt->addMinutes($settings->durationMinutes);

        $consultation = DB::transaction(function () use ($lead, $startsAt, $endsAt, $channel, $customerNotes, $actor, $advisorId, $settings, $minNotice, $rescheduledFrom) {
            $booking = null;
            $advisor = null;

            foreach ($this->rankAdvisors($startsAt, $advisorId, $settings, $minNotice) as $candidate) {
                try {
                    $booking = $this->bookings->reserve(
                        $candidate,
                        BookingKind::Consultation,
                        $startsAt,
                        $endsAt,
                        $settings->bufferMinutes,
                    );
                    $advisor = $candidate;

                    break;
                } catch (SlotUnavailableException) {
                    continue;
                }
            }

            if ($booking === null || $advisor === null) {
                throw new SlotUnavailableException;
            }

            $consultation = Consultation::query()->create([
                'folio' => Folio::next('A'),
                'lead_id' => $lead->id,
                'advisor_id' => $advisor->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => ConsultationStatus::Scheduled,
                'channel' => $channel,
                'meeting_provider' => $channel === ConsultationChannel::Video ? $this->meetings->key() : null,
                'meeting_url' => $rescheduledFrom?->meeting_url,
                'price' => $rescheduledFrom !== null ? $rescheduledFrom->price : $settings->price->amount,
                'customer_notes' => $customerNotes ?? $rescheduledFrom?->customer_notes,
                'internal_notes' => $rescheduledFrom?->internal_notes,
                'rescheduled_from_id' => $rescheduledFrom?->id,
                'created_by' => $actor?->id,
            ]);

            $this->bookings->attach($booking, $consultation);

            if ($channel === ConsultationChannel::Video) {
                $url = $rescheduledFrom ? $this->meetings->update($consultation) : $this->meetings->create($consultation);
                if ($url !== null && $url !== $consultation->meeting_url) {
                    $consultation->update(['meeting_url' => $url]);
                }
            }

            if (! $rescheduledFrom) {
                $this->workflow->transition($lead, LeadStatus::ConsultationScheduled, $actor, 'Asesoría agendada', force: true);
            }

            $this->workflow->record(
                $lead,
                $rescheduledFrom ? LeadActivityType::ConsultationUpdated : LeadActivityType::ConsultationScheduled,
                ($rescheduledFrom ? 'Asesoría reprogramada para el ' : 'Asesoría agendada para el ').When::long($startsAt)." con {$advisor->name}",
                $actor,
                ['consultation_id' => $consultation->id, 'channel' => $channel->value],
            );

            $this->logger->log($rescheduledFrom ? 'asesoria.reprogramada' : 'asesoria.agendada', $consultation, [
                'after' => ['starts_at' => $startsAt->toIso8601String(), 'advisor_id' => $advisor->id, 'channel' => $channel->value],
                'rescheduled_from' => $rescheduledFrom?->id,
            ]);

            return $consultation;
        });

        $this->notifyBooked($consultation, $rescheduledFrom !== null, $actor);

        return $consultation;
    }

    /** Reprograma: libera el horario anterior y reserva el nuevo en una sola transacción. */
    public function reschedule(Consultation $consultation, CarbonImmutable $startsAt, ?User $actor = null, bool $staffBooking = false, ?ConsultationChannel $channel = null, ?int $advisorId = null): Consultation
    {
        $previousAdvisor = $consultation->advisor_id;

        $new = DB::transaction(function () use ($consultation, $startsAt, $actor, $staffBooking, $channel, $previousAdvisor, $advisorId) {
            /** @var Consultation $locked */
            $locked = Consultation::query()->whereKey($consultation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isActive()) {
                throw ValidationException::withMessages(['starts_at' => 'Esta asesoría ya no se puede reprogramar.']);
            }

            $this->releaseBookings($locked);

            $locked->update(['status' => ConsultationStatus::Rescheduled]);

            // Asesor elegido por el equipo: sin alternativa.
            if ($advisorId !== null) {
                return $this->bookInsideTransaction($locked, $startsAt, $actor, $staffBooking, $channel, $advisorId);
            }

            // Se intenta mantener al mismo asesor; si no tiene libre, cualquiera.
            try {
                $new = $this->bookInsideTransaction($locked, $startsAt, $actor, $staffBooking, $channel, $previousAdvisor);
            } catch (SlotUnavailableException) {
                $new = $this->bookInsideTransaction($locked, $startsAt, $actor, $staffBooking, $channel, null);
            }

            return $new;
        });

        return $new;
    }

    private function bookInsideTransaction(Consultation $old, CarbonImmutable $startsAt, ?User $actor, bool $staffBooking, ?ConsultationChannel $channel, ?int $advisorId): Consultation
    {
        return $this->book(
            $old->lead,
            $startsAt,
            $channel ?? $old->channel,
            null,
            $actor,
            $advisorId,
            $staffBooking,
            $old,
        );
    }

    /** Cancela (idempotente) y libera el horario. */
    public function cancel(Consultation $consultation, ?string $reason = null, ?User $actor = null, bool $notifyCustomer = true): Consultation
    {
        $changed = false;

        $result = DB::transaction(function () use ($consultation, $reason, $actor, &$changed) {
            /** @var Consultation $locked */
            $locked = Consultation::query()->whereKey($consultation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ConsultationStatus::Cancelled) {
                return $locked;
            }
            if (! $locked->status->isActive()) {
                throw ValidationException::withMessages(['status' => 'Esta asesoría ya no se puede cancelar.']);
            }

            $this->releaseBookings($locked);
            $this->meetings->cancel($locked);

            $locked->update([
                'status' => ConsultationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason ? mb_substr($reason, 0, 200) : null,
            ]);

            $this->workflow->record($locked->lead, LeadActivityType::ConsultationUpdated, 'Asesoría cancelada'.($reason ? ": {$reason}" : ''), $actor, ['consultation_id' => $locked->id]);
            $this->logger->log('asesoria.cancelada', $locked, ['reason' => $reason]);
            $changed = true;

            return $locked;
        });

        if ($changed) {
            $this->safely(function () use ($result, $actor, $notifyCustomer) {
                $result->loadMissing(['lead.customer', 'advisor']);
                if ($notifyCustomer) {
                    CustomerNotifier::send($result->lead->customer, new ConsultationCancelled($result));
                }
                if ($result->advisor->id !== $actor?->id) {
                    $result->advisor->notify(new ConsultationAdvisorAlert($result, 'cancelled'));
                }
            });
        }

        return $result;
    }

    /** Confirmada / realizada / no se presentó. */
    public function markStatus(Consultation $consultation, ConsultationStatus $to, User $actor): Consultation
    {
        if (! in_array($to, [ConsultationStatus::Confirmed, ConsultationStatus::Completed, ConsultationStatus::NoShow], true)) {
            throw ValidationException::withMessages(['status' => 'Estado no válido.']);
        }

        return DB::transaction(function () use ($consultation, $to, $actor) {
            /** @var Consultation $locked */
            $locked = Consultation::query()->whereKey($consultation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $to) {
                return $locked;
            }
            if (! $locked->status->isActive() && ! ($locked->status === ConsultationStatus::NoShow && $to === ConsultationStatus::Completed)) {
                throw ValidationException::withMessages(['status' => 'Esta asesoría ya no admite ese cambio.']);
            }
            if ($to === ConsultationStatus::Confirmed && $locked->starts_at->isPast()) {
                throw ValidationException::withMessages(['status' => 'La asesoría ya pasó; márcala como realizada o como no presentada.']);
            }

            $before = $locked->status;
            $locked->update([
                'status' => $to,
                'completed_at' => $to === ConsultationStatus::Completed ? now() : $locked->completed_at,
            ]);

            $this->workflow->record($locked->lead, LeadActivityType::ConsultationUpdated, "Asesoría: {$to->label()}", $actor, ['consultation_id' => $locked->id]);
            $this->logger->log('asesoria.estado', $locked, ['before' => ['status' => $before->value], 'after' => ['status' => $to->value]]);

            return $locked;
        });
    }

    /** Registra el resultado; avanza el prospecto solo en resultados positivos. */
    public function recordOutcome(Consultation $consultation, ConsultationOutcome $outcome, ?string $notes, User $actor): Consultation
    {
        return DB::transaction(function () use ($consultation, $outcome, $notes, $actor) {
            /** @var Consultation $locked */
            $locked = Consultation::query()->whereKey($consultation->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [ConsultationStatus::Cancelled, ConsultationStatus::Rescheduled], true)) {
                throw ValidationException::withMessages(['outcome' => 'No se puede registrar resultado de una asesoría cancelada o reprogramada.']);
            }

            $locked->update([
                'outcome' => $outcome,
                'outcome_notes' => $notes,
                'status' => ConsultationStatus::Completed,
                'completed_at' => $locked->completed_at ?? now(),
            ]);

            $lead = $locked->lead;
            $target = match ($outcome) {
                ConsultationOutcome::InterestedVisit => LeadStatus::VisitPending,
                ConsultationOutcome::Prequalification => LeadStatus::Prequalification,
                default => null,
            };
            if ($target) {
                $this->workflow->transition($lead, $target, $actor, "Resultado de asesoría: {$outcome->label()}", force: true);
            }

            $this->workflow->record($lead, LeadActivityType::ConsultationUpdated, "Resultado de asesoría: {$outcome->label()}".($notes ? " — {$notes}" : ''), $actor, [
                'consultation_id' => $locked->id,
                'outcome' => $outcome->value,
            ]);
            $this->logger->log('asesoria.resultado', $locked, ['after' => ['outcome' => $outcome->value]]);

            return $locked;
        });
    }

    /**
     * Asesores elegibles para un horario, del menos al más cargado ese día.
     *
     * @return Collection<int, User>
     */
    private function rankAdvisors(CarbonImmutable $startsAt, ?int $advisorId, ConsultationSettings $settings, int $minNotice): Collection
    {
        $advisors = $this->advisors($advisorId)->filter(function (User $advisor) use ($startsAt, $settings, $minNotice) {
            foreach ($this->slotsForAdvisor($advisor, $startsAt, $settings, $minNotice) as $slot) {
                if ($slot['starts_at']->equalTo($startsAt)) {
                    return true;
                }
            }

            return false;
        });

        if ($advisors->isEmpty()) {
            return $advisors;
        }

        $load = CalendarBooking::query()
            ->whereIn('user_id', $advisors->pluck('id'))
            ->where('kind', BookingKind::Consultation->value)
            ->occupying()
            ->whereBetween('starts_at', [$startsAt->startOfDay(), $startsAt->endOfDay()])
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as total')
            ->pluck('total', 'user_id');

        return $advisors->sortBy(fn (User $u) => [(int) ($load[$u->id] ?? 0), $u->id])->values();
    }

    private function releaseBookings(Consultation $consultation): void
    {
        CalendarBooking::query()
            ->where('bookable_type', $consultation->getMorphClass())
            ->where('bookable_id', $consultation->id)
            ->where('status', '!=', BookingStatus::Released->value)
            ->get()
            ->each(fn (CalendarBooking $b) => $this->bookings->release($b));
    }

    private function notifyBooked(Consultation $consultation, bool $rescheduled, ?User $actor): void
    {
        $this->safely(function () use ($consultation, $rescheduled, $actor) {
            $consultation->loadMissing(['lead.customer', 'advisor']);
            CustomerNotifier::send($consultation->lead->customer, new ConsultationBooked($consultation, $rescheduled));
            if ($consultation->advisor->id !== $actor?->id) {
                $consultation->advisor->notify(new ConsultationAdvisorAlert($consultation, $rescheduled ? 'rescheduled' : 'booked'));
            }
        });
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('crm.consultation_notification_failed', ['error' => $e->getMessage()]);
        }
    }
}
