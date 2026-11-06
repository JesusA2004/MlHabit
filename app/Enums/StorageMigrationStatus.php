<?php

namespace App\Enums;

enum StorageMigrationStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Running => 'En curso',
            self::Completed => 'Completado',
            self::CompletedWithErrors => 'Completado con errores',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
