<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WebhookEventStatus: string
{
    use HasOptions;

    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibido',
            self::Processed => 'Procesado',
            self::Ignored => 'Ignorado',
            self::Failed => 'Con error',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::Processed => 'success',
            self::Ignored => 'neutral',
            self::Failed => 'danger',
        };
    }
}
