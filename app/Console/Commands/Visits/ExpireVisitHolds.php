<?php

namespace App\Console\Commands\Visits;

use App\Services\Scheduling\BookingService;
use App\Services\Visits\VisitHoldExpirer;
use Illuminate\Console\Command;

/** Vence apartados de visitas no pagadas y libera sus horarios. Programar cada minuto. */
class ExpireVisitHolds extends Command
{
    protected $signature = 'visits:expire-holds';

    protected $description = 'Vence apartados de visitas sin pago y libera el horario';

    public function handle(VisitHoldExpirer $expirer, BookingService $bookings): int
    {
        $visits = $expirer->expireDue();
        $released = $bookings->releaseExpiredHolds();

        $this->info("Visitas vencidas: {$visits} · apartados de agenda liberados: {$released}");

        return self::SUCCESS;
    }
}
