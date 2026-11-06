<?php

namespace App\Services\Crm\Meetings;

use App\Models\Consultation;
use App\Support\Settings;

/**
 * Proveedor vigente según Configuración → Asesorías (`consultation.meeting_provider`):
 * "jitsi" (por defecto, enlace automático) o "manual" (el asesor lo pega).
 * Se decide en cada llamada, así un cambio en el panel aplica de inmediato.
 */
class ConfiguredMeetingProvider implements MeetingProvider
{
    public const OPTIONS = ['jitsi', 'manual'];

    public function __construct(
        private readonly JitsiMeetingProvider $jitsi,
        private readonly ManualMeetingProvider $manual,
    ) {}

    public static function configuredKey(): string
    {
        $key = Settings::get('consultation.meeting_provider', 'jitsi');

        return is_string($key) && in_array($key, self::OPTIONS, true) ? $key : 'jitsi';
    }

    public function current(): MeetingProvider
    {
        return self::configuredKey() === 'manual' ? $this->manual : $this->jitsi;
    }

    public function key(): string
    {
        return $this->current()->key();
    }

    public function create(Consultation $consultation): ?string
    {
        return $this->current()->create($consultation);
    }

    public function update(Consultation $consultation): ?string
    {
        return $this->current()->update($consultation);
    }

    public function cancel(Consultation $consultation): void
    {
        $this->current()->cancel($consultation);
    }
}
