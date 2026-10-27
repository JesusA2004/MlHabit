<?php

namespace App\Services\Crm;

use App\Enums\AvailabilityExceptionType;
use App\Enums\BookingKind;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\CalendarBooking;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilidad semanal y excepciones de asesores/técnicos. Valida en
 * servidor que los rangos sean coherentes y no se traslapen.
 */
class AgendaService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Reemplaza el horario semanal de una persona para una agenda.
     *
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $ranges
     */
    public function replaceRules(User $user, BookingKind $kind, array $ranges, ?string $effectiveFrom, ?string $effectiveUntil): void
    {
        $errors = [];
        $byDay = [];

        foreach ($ranges as $i => $range) {
            $start = self::minutes($range['starts_at']);
            $end = self::minutes($range['ends_at']);
            if ($end <= $start) {
                $errors["ranges.{$i}.ends_at"] = 'La hora de fin debe ser posterior a la de inicio.';

                continue;
            }
            $byDay[(int) $range['weekday']][] = [$start, $end, $i];
        }

        foreach ($byDay as $list) {
            usort($list, fn ($a, $b) => $a[0] <=> $b[0]);
            for ($k = 1; $k < count($list); $k++) {
                if ($list[$k][0] < $list[$k - 1][1]) {
                    $errors["ranges.{$list[$k][2]}.starts_at"] = 'Este horario se encima con otro del mismo día.';
                }
            }
        }

        if ($effectiveFrom && $effectiveUntil && $effectiveUntil < $effectiveFrom) {
            $errors['effective_until'] = 'La fecha final debe ser igual o posterior a la inicial.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($user, $kind, $ranges, $effectiveFrom, $effectiveUntil) {
            $before = AvailabilityRule::query()->where('user_id', $user->id)->where('kind', $kind->value)->count();
            AvailabilityRule::query()->where('user_id', $user->id)->where('kind', $kind->value)->delete();

            foreach ($ranges as $range) {
                AvailabilityRule::query()->create([
                    'user_id' => $user->id,
                    'kind' => $kind,
                    'weekday' => (int) $range['weekday'],
                    'starts_at' => self::normalizeTime($range['starts_at']),
                    'ends_at' => self::normalizeTime($range['ends_at']),
                    'effective_from' => $effectiveFrom,
                    'effective_until' => $effectiveUntil,
                    'is_active' => true,
                ]);
            }

            $this->logger->log('agenda.horario_actualizado', $user, [
                'kind' => $kind->value,
                'before' => ['rules' => $before],
                'after' => ['rules' => count($ranges), 'effective_from' => $effectiveFrom, 'effective_until' => $effectiveUntil],
            ]);
        });
    }

    /**
     * Crea o actualiza una excepción. Devuelve cuántas reservas activas
     * quedan dentro de un bloqueo (no se cancelan solas: se avisa).
     *
     * @param  array{type: string, kind?: string|null, starts_at: string, ends_at: string, reason?: string|null}  $data
     */
    public function saveException(User $user, array $data, User $actor, ?AvailabilityException $exception = null): int
    {
        $type = AvailabilityExceptionType::from($data['type']);
        $kind = ! empty($data['kind']) ? BookingKind::from($data['kind']) : null;
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => 'El fin debe ser posterior al inicio.']);
        }
        if ($type === AvailabilityExceptionType::Extra && ! $start->isSameDay($end)) {
            throw ValidationException::withMessages(['ends_at' => 'Un horario adicional debe empezar y terminar el mismo día.']);
        }

        $overlap = AvailabilityException::query()
            ->where('user_id', $user->id)
            ->when($exception, fn ($q) => $q->whereKeyNot($exception->id))
            ->where(fn ($q) => $kind ? $q->whereNull('kind')->orWhere('kind', $kind->value) : $q)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Ya hay otra excepción en ese periodo. Edítala o elige otras fechas.']);
        }

        DB::transaction(function () use ($user, $type, $kind, $start, $end, $data, $actor, $exception) {
            $payload = [
                'user_id' => $user->id,
                'kind' => $kind,
                'type' => $type,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => $data['reason'] ?? null,
            ];

            if ($exception) {
                $exception->update($payload);
            } else {
                $exception = AvailabilityException::query()->create([...$payload, 'created_by' => $actor->id]);
            }

            $this->logger->log('agenda.excepcion_guardada', $exception, ['after' => [
                'type' => $type->value, 'kind' => $kind?->value, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
            ]]);
        });

        if (! $type->removesTime()) {
            return 0;
        }

        return CalendarBooking::query()
            ->where('user_id', $user->id)
            ->when($kind, fn ($q) => $q->where('kind', $kind->value))
            ->occupying()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->count();
    }

    public function deleteException(AvailabilityException $exception): void
    {
        $this->logger->log('agenda.excepcion_eliminada', $exception, ['before' => [
            'type' => $exception->type->value,
            'starts_at' => $exception->starts_at->toIso8601String(),
            'ends_at' => $exception->ends_at->toIso8601String(),
        ]]);
        $exception->delete();
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }

    private static function normalizeTime(string $time): string
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return sprintf('%02d:%02d:00', $h, $m);
    }
}
