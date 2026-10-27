<?php

namespace App\Console\Commands;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Notifications\Crm\ConsultationReminder;
use App\Notifications\Crm\CustomerNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recordatorios de asesoría: 24 h y 1 h antes. Idempotente con
 * `reminder_sent_at` (se "reclama" con un UPDATE condicional, así dos
 * ejecuciones simultáneas nunca envían el mismo recordatorio):
 *
 *  - 24 h: si aún no se envió ninguno y la cita se agendó con ≥ 24 h.
 *  - 1 h: si el último envío fue antes de las 2 h previas a la cita.
 *
 * Programar cada 5 minutos: Schedule::command('consultations:send-reminders')->everyFiveMinutes();
 */
#[Signature('consultations:send-reminders')]
#[Description('Envía recordatorios de asesorías (24 h y 1 h antes)')]
class SendConsultationReminders extends Command
{
    public function handle(): int
    {
        $sent = ['24h' => 0, '1h' => 0];
        $now = now();

        // 1 h antes (ventana de 75 min para tolerar retrasos del scheduler).
        $this->dueQuery()
            ->whereBetween('starts_at', [$now, $now->addMinutes(75)])
            ->get()
            ->each(function (Consultation $c) use (&$sent) {
                $claimed = Consultation::query()->whereKey($c->id)
                    ->where(fn (Builder $q) => $q->whereNull('reminder_sent_at')->orWhere('reminder_sent_at', '<', $c->starts_at->subHours(2)))
                    ->update(['reminder_sent_at' => now()]);
                if ($claimed) {
                    $this->deliver($c, '1h');
                    $sent['1h']++;
                }
            });

        // 24 h antes.
        $this->dueQuery()
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now->addMinutes(75))
            ->where('starts_at', '<=', $now->addHours(24))
            ->get()
            ->filter(fn (Consultation $c) => $c->created_at === null || $c->created_at->diffInHours($c->starts_at) >= 24)
            ->each(function (Consultation $c) use (&$sent) {
                $claimed = Consultation::query()->whereKey($c->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if ($claimed) {
                    $this->deliver($c, '24h');
                    $sent['24h']++;
                }
            });

        $this->info("Recordatorios enviados: {$sent['24h']} (24 h), {$sent['1h']} (1 h).");

        return self::SUCCESS;
    }

    /** @return Builder<Consultation> */
    private function dueQuery(): Builder
    {
        return Consultation::query()
            ->whereIn('status', [ConsultationStatus::Scheduled->value, ConsultationStatus::Confirmed->value])
            ->with(['lead.customer', 'advisor']);
    }

    /** @param '24h'|'1h' $window */
    private function deliver(Consultation $consultation, string $window): void
    {
        CustomerNotifier::send($consultation->lead->customer, new ConsultationReminder($consultation, $window));
    }
}
