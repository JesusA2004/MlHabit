<?php

namespace App\Services\Crm;

use App\Enums\ConsultationChannel;
use App\Support\Money;
use App\Support\Settings;

/**
 * Configuración vigente de asesorías (grupo `consultation` de Settings)
 * normalizada y con límites sanos.
 *
 * Toda asesoría tiene costo: si el precio guardado no es positivo (dato
 * antiguo o manipulado) se usa DEFAULT_PRICE. El panel valida > 0.
 */
final class ConsultationSettings
{
    /** Precio provisional si nunca se configuró uno válido (MXN). */
    public const DEFAULT_PRICE = '500.00';

    /** Minutos que el horario queda apartado mientras el cliente paga en línea. */
    public const HOLD_MINUTES = 15;

    /** Horas de apartado cuando el equipo agenda y envía el enlace de pago. */
    public const STAFF_HOLD_HOURS = 24;

    /**
     * @param  list<ConsultationChannel>  $channels
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly int $durationMinutes,
        public readonly int $bufferMinutes,
        public readonly Money $price,
        public readonly int $bookingWindowDays,
        public readonly int $minNoticeHours,
        public readonly array $channels,
        public readonly int $stepMinutes = 30,
    ) {}

    public static function current(): self
    {
        $s = Settings::group('consultation');

        $channels = array_values(array_filter(array_map(
            fn ($c) => is_string($c) ? ConsultationChannel::tryFrom($c) : null,
            (array) ($s['channels'] ?? []),
        )));

        $duration = max(10, min(240, (int) ($s['duration_minutes'] ?? 30)));

        return new self(
            enabled: (bool) ($s['enabled'] ?? true),
            durationMinutes: $duration,
            bufferMinutes: max(0, min(120, (int) ($s['buffer_minutes'] ?? 0))),
            price: self::normalizePrice($s['price'] ?? null),
            bookingWindowDays: max(1, min(120, (int) ($s['booking_window_days'] ?? 21))),
            minNoticeHours: max(0, min(168, (int) ($s['min_notice_hours'] ?? 0))),
            channels: $channels !== [] ? $channels : ConsultationChannel::cases(),
            stepMinutes: $duration <= 20 ? 15 : 30,
        );
    }

    public static function normalizePrice(mixed $value): Money
    {
        $price = is_numeric($value) ? Money::of((string) $value) : null;

        return $price !== null && $price->isPositive() ? $price : Money::of(self::DEFAULT_PRICE);
    }

    /** Ya no existen asesorías sin costo; se conserva por compatibilidad de props. */
    public function isFree(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'durationMinutes' => $this->durationMinutes,
            'price' => $this->price->amount,
            'isFree' => false,
            'holdMinutes' => self::HOLD_MINUTES,
            'bookingWindowDays' => $this->bookingWindowDays,
            'minNoticeHours' => $this->minNoticeHours,
            'channels' => array_map(fn (ConsultationChannel $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'description' => $c->description(),
            ], $this->channels),
        ];
    }
}
