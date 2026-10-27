<?php

namespace App\Console\Commands\Visits;

use App\Enums\VisitStatus;
use App\Models\SiteVisit;
use App\Services\Visits\VisitNotifier;
use Illuminate\Console\Command;

/**
 * Recordatorio 24 h antes de cada visita agendada. Idempotente: marca
 * reminder_sent_at con un UPDATE condicional, así dos ejecuciones
 * simultáneas nunca envían dos veces. Programar cada 15 minutos.
 */
class SendVisitReminders extends Command
{
    protected $signature = 'visits:send-reminders {--hours=24}';

    protected $description = 'Envía recordatorios de visitas técnicas próximas';

    public function handle(VisitNotifier $notifier): int
    {
        $until = now()->addHours(max(1, (int) $this->option('hours')));
        $sent = 0;

        $visits = SiteVisit::query()
            ->whereIn('status', [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value])
            ->whereNull('reminder_sent_at')
            ->whereNull('attention_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', $until)
            ->orderBy('starts_at')
            ->get();

        foreach ($visits as $visit) {
            $claimed = SiteVisit::query()->whereKey($visit->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);

            if ($claimed === 1) {
                $notifier->reminder($visit);
                $sent++;
            }
        }

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
