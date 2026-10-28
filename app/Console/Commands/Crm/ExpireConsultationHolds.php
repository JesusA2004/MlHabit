<?php

namespace App\Console\Commands\Crm;

use App\Services\Crm\ConsultationHoldExpirer;
use Illuminate\Console\Command;

/** Cancela asesorías cuyo apartado venció sin pago y libera el horario. Programar cada minuto. */
class ExpireConsultationHolds extends Command
{
    protected $signature = 'consultations:expire-holds';

    protected $description = 'Cancela asesorías no pagadas cuyo apartado venció y libera el horario';

    public function handle(ConsultationHoldExpirer $expirer): int
    {
        $count = $expirer->expireDue();

        $this->info("Asesorías liberadas por falta de pago: {$count}");

        return self::SUCCESS;
    }
}
