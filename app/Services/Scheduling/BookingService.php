<?php

namespace App\Services\Scheduling;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\CalendarBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Reserva de horarios a prueba de condiciones de carrera.
 *
 * Garantías:
 *  1. Se bloquea la fila del recurso (users … FOR UPDATE): dos reservas
 *     simultáneas para la misma persona se serializan.
 *  2. Dentro del bloqueo se verifica traslape (incluido el buffer) contra
 *     reservas confirmadas y apartados vigentes, y que el horario siga
 *     dentro de la disponibilidad (SlotFinder).
 *  3. Respaldo en DB: slot_key (usuario|inicio) es UNIQUE mientras la
 *     reserva está activa.
 *
 * Debe llamarse dentro de la transacción del flujo de negocio cuando se
 * crea también la entidad (visita/asesoría), para que todo sea atómico.
 */
class BookingService
{
    public function __construct(private readonly SlotFinder $slots) {}

    /**
     * Aparta (held) o confirma directamente un horario.
     *
     * @throws SlotUnavailableException
     */
    public function reserve(
        User $resource,
        BookingKind $kind,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $bufferMinutes = 0,
        ?CarbonImmutable $holdUntil = null,
        ?Model $bookable = null,
        bool $checkAvailability = true,
    ): CalendarBooking {
        return DB::transaction(function () use ($resource, $kind, $startsAt, $endsAt, $bufferMinutes, $holdUntil, $bookable, $checkAvailability) {
            User::query()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();

            $this->releaseExpiredHolds($resource);

            if ($checkAvailability && ! $this->slots->isWithinAvailability($resource, $kind, $startsAt, $endsAt)) {
                throw new SlotUnavailableException('Ese horario ya no forma parte de la agenda disponible.');
            }

            if ($this->overlaps($resource, $startsAt, $endsAt, $bufferMinutes)) {
                throw new SlotUnavailableException;
            }

            try {
                return CalendarBooking::query()->create([
                    'user_id' => $resource->getKey(),
                    'kind' => $kind,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'buffer_minutes' => $bufferMinutes,
                    'bookable_type' => $bookable?->getMorphClass(),
                    'bookable_id' => $bookable?->getKey(),
                    'status' => $holdUntil ? BookingStatus::Held : BookingStatus::Confirmed,
                    'hold_expires_at' => $holdUntil,
                    'slot_key' => self::slotKey($resource, $startsAt),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new SlotUnavailableException;
            }
        });
    }

    /** Convierte un apartado en reserva firme (p. ej. al confirmarse el pago). */
    public function confirm(CalendarBooking $booking): CalendarBooking
    {
        return DB::transaction(function () use ($booking) {
            /** @var CalendarBooking $locked */
            $locked = CalendarBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === BookingStatus::Confirmed) {
                return $locked;
            }

            if ($locked->status === BookingStatus::Released) {
                // El apartado expiró y se liberó: se intenta recuperar el
                // mismo horario si sigue libre (pago confirmado tarde).
                if ($this->overlaps($locked->user, CarbonImmutable::parse($locked->starts_at), CarbonImmutable::parse($locked->ends_at), $locked->buffer_minutes, $locked->id)) {
                    throw new SlotUnavailableException('El horario apartado se liberó y ya fue tomado por otra reserva.');
                }

                try {
                    $locked->slot_key = self::slotKey($locked->user, CarbonImmutable::parse($locked->starts_at));
                    $locked->save();
                } catch (UniqueConstraintViolationException) {
                    throw new SlotUnavailableException('El horario apartado se liberó y ya fue tomado por otra reserva.');
                }
            }

            $locked->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);

            return $locked;
        });
    }

    /** Libera un horario (cancelación, pago fallido, reprogramación). Idempotente. */
    public function release(CalendarBooking $booking): void
    {
        CalendarBooking::query()
            ->whereKey($booking->id)
            ->where('status', '!=', BookingStatus::Released->value)
            ->update(['status' => BookingStatus::Released->value, 'slot_key' => null, 'updated_at' => now()]);
    }

    public function attach(CalendarBooking $booking, Model $bookable): void
    {
        $booking->update(['bookable_type' => $bookable->getMorphClass(), 'bookable_id' => $bookable->getKey()]);
    }

    /** Libera apartados vencidos (global o de un recurso). Devuelve cuántos. */
    public function releaseExpiredHolds(?User $resource = null): int
    {
        return CalendarBooking::query()
            ->where('status', BookingStatus::Held->value)
            ->where('hold_expires_at', '<=', now())
            ->when($resource, fn ($q) => $q->where('user_id', $resource->getKey()))
            ->update(['status' => BookingStatus::Released->value, 'slot_key' => null, 'updated_at' => now()]);
    }

    public function overlaps(User $resource, CarbonImmutable $startsAt, CarbonImmutable $endsAt, int $bufferMinutes = 0, ?int $ignoreId = null): bool
    {
        $from = $startsAt->subMinutes($bufferMinutes);
        $to = $endsAt->addMinutes($bufferMinutes);

        return CalendarBooking::query()
            ->where('user_id', $resource->getKey())
            ->occupying()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->exists();
    }

    public static function slotKey(User $resource, CarbonImmutable $startsAt): string
    {
        return $resource->getKey().'|'.$startsAt->format('Y-m-d H:i');
    }
}
