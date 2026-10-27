<?php

namespace App\Services\Crm;

use App\Enums\ConsultationChannel;
use App\Support\Money;
use App\Support\Settings;

/**
 * Configuración vigente de asesorías (grupo `consultation` de Settings)
 * normalizada y con límites sanos.
 */
final class ConsultationSettings
{
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
            price: Money::of((string) ($s['price'] ?? '0')),
            bookingWindowDays: max(1, min(120, (int) ($s['booking_window_days'] ?? 21))),
            minNoticeHours: max(0, min(168, (int) ($s['min_notice_hours'] ?? 0))),
            channels: $channels !== [] ? $channels : ConsultationChannel::cases(),
            stepMinutes: $duration <= 20 ? 15 : 30,
        );
    }

    public function isFree(): bool
    {
        return ! $this->price->isPositive();
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
            'isFree' => $this->isFree(),
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
