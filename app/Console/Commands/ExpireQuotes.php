<?php

namespace App\Console\Commands;

use App\Services\Projects\QuoteService;
use Illuminate\Console\Command;

/**
 * Marca como vencidas las cotizaciones enviadas cuya vigencia terminó.
 * Programar a diario (00:15): Schedule::command('quotes:expire')->dailyAt('00:15').
 */
class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Marca como vencidas las cotizaciones enviadas fuera de vigencia';

    public function handle(QuoteService $quotes): int
    {
        $count = $quotes->expireDue();
        $this->info("Cotizaciones vencidas: {$count}");

        return self::SUCCESS;
    }
}
