<?php

namespace App\Console\Commands;

use App\Cms\Layout\HomeRedesign;
use Illuminate\Console\Command;

/**
 * Publica la portada rediseñada (v3) de forma segura: respalda lo
 * publicado en el historial del editor y solo reemplaza una portada sin
 * personalizar (ver App\Cms\Layout\HomeRedesign). La migración
 * `2026_11_06_000200_publish_redesigned_home` lo ejecuta en cada despliegue.
 */
class RedesignHomeCommand extends Command
{
    protected $signature = 'mlhabitat:redesign-home
        {--force : Publica la portada nueva aunque el equipo haya personalizado la actual (la actual queda respaldada)}';

    protected $description = 'Publica la portada rediseñada respaldando la anterior en el historial del editor';

    public function handle(HomeRedesign $redesign): int
    {
        $result = $redesign->apply((bool) $this->option('force'));

        $this->info(match ($result) {
            HomeRedesign::ALREADY => 'La portada rediseñada ya se aplicó antes; no se cambió nada.',
            HomeRedesign::INSTALLED => 'Se publicó la portada rediseñada (no había portada).',
            HomeRedesign::REPLACED => 'Se publicó la portada rediseñada. La anterior quedó en el historial: «'.HomeRedesign::BACKUP_NOTE.'».',
            default => 'La portada actual está personalizada y no se tocó. La nueva quedó en el historial: «'.HomeRedesign::OFFERED_NOTE.'».',
        });

        return self::SUCCESS;
    }
}
