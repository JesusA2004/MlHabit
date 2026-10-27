<?php

namespace App\Services\Crm;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Notifications\Crm\LeadAssigned;
use App\Services\ActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para cambiar la etapa de un prospecto y para escribir en su
 * bitácora comercial (LeadActivity) + auditoría (ActivityLogger).
 *
 * Contrato para otros módulos (visitas, proyectos):
 *
 *     app(LeadWorkflow::class)->transition($lead, LeadStatus::VisitPaid, force: true);
 *
 *  - Idempotente: pedir el estado actual no hace nada.
 *  - `$force = true` (transiciones de SISTEMA): omite la validación de la
 *    matriz de transiciones, pero SOLO avanza. Si el prospecto ya está en
 *    una etapa posterior (o cerrado), la llamada no hace nada y devuelve el
 *    prospecto sin error. Así, p. ej., un pago de visita confirmado tarde
 *    nunca regresa un prospecto que ya está en diseño.
 *  - Sin `$force` (acciones del equipo): valida con
 *    LeadStatus::canTransitionTo() y lanza ValidationException.
 *  - Marcar como perdido exige motivo (`$attributes['lost_reason']`).
 */
class LeadWorkflow
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{lost_reason?: LostReason|string|null, lost_notes?: string|null}  $attributes
     */
    public function transition(
        Lead $lead,
        LeadStatus $to,
        ?User $actor = null,
        ?string $note = null,
        bool $force = false,
        array $attributes = [],
    ): Lead {
        return DB::transaction(function () use ($lead, $to, $actor, $note, $force, $attributes) {
            /** @var Lead $locked */
            $locked = Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                $this->syncInstance($lead, $locked);

                return $locked;
            }

            if ($force) {
                // Sistema: solo hacia adelante y nunca sobre un prospecto cerrado.
                if ($from->isClosed() || $to->rank() <= $from->rank() && ! $to->isClosed()) {
                    $this->syncInstance($lead, $locked);

                    return $locked;
                }
            } elseif (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de «{$from->label()}» a «{$to->label()}».",
                ]);
            }

            $changes = ['status' => $to];

            if ($to === LeadStatus::Lost) {
                $reason = $attributes['lost_reason'] ?? null;
                $reason = is_string($reason) ? LostReason::tryFrom($reason) : $reason;
                if ($reason === null) {
                    throw ValidationException::withMessages(['lost_reason' => 'Indica por qué se perdió el prospecto.']);
                }
                $changes['lost_reason'] = $reason;
                $changes['lost_notes'] = $attributes['lost_notes'] ?? $note;
            }

            if ($to->isClosed()) {
                $changes['closed_at'] = now();
            } elseif ($from->isClosed()) {
                // Reapertura.
                $changes['closed_at'] = null;
                $changes['lost_reason'] = null;
                $changes['lost_notes'] = null;
            }

            if ($locked->first_contacted_at === null && $from === LeadStatus::New && ! in_array($to, [LeadStatus::Cancelled], true)) {
                $changes['first_contacted_at'] = now();
            }

            $locked->update($changes);

            $this->record($locked, LeadActivityType::StatusChanged, $note, $actor, [
                'forced' => $force ?: null,
                'lost_reason' => ($changes['lost_reason'] ?? null)?->value,
            ], $from, $to);

            $this->logger->log('prospecto.estado', $locked, [
                'before' => ['status' => $from->value],
                'after' => ['status' => $to->value],
                'forced' => $force,
            ]);

            $this->syncInstance($lead, $locked);

            return $locked;
        });
    }

    /**
     * Agrega una entrada a la bitácora del prospecto (sin cambiar etapa).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Lead $lead,
        LeadActivityType $type,
        ?string $body = null,
        ?User $actor = null,
        array $metadata = [],
        ?LeadStatus $from = null,
        ?LeadStatus $to = null,
    ): LeadActivity {
        $metadata = array_filter($metadata, fn ($v) => $v !== null);

        return LeadActivity::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $actor !== null ? $actor->id : auth()->id(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'body' => $body,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra una interacción manual (nota, llamada…). Una llamada o mensaje
     * a un prospecto nuevo cuenta como primer contacto.
     */
    public function logInteraction(Lead $lead, LeadActivityType $type, string $body, User $actor): LeadActivity
    {
        return DB::transaction(function () use ($lead, $type, $body, $actor) {
            $activity = $this->record($lead, $type, $body, $actor);

            if ($type !== LeadActivityType::Note && $lead->first_contacted_at === null) {
                $lead->update(['first_contacted_at' => now()]);
            }

            $this->logger->log('prospecto.interaccion', $lead, ['type' => $type->value]);

            return $activity;
        });
    }

    /** Asigna (o quita) el responsable del prospecto y avisa al nuevo responsable. */
    public function assign(Lead $lead, ?User $assignee, ?User $actor = null, bool $notify = true): Lead
    {
        $previous = $lead->assigned_to;
        if ($previous === $assignee?->id) {
            return $lead;
        }

        DB::transaction(function () use ($lead, $assignee, $actor, $previous) {
            $lead->update(['assigned_to' => $assignee?->id]);

            $this->record($lead, LeadActivityType::Assigned, $assignee ? "Asignado a {$assignee->name}" : 'Sin responsable', $actor, [
                'from_user_id' => $previous,
                'to_user_id' => $assignee?->id,
            ]);

            $this->logger->log('prospecto.asignado', $lead, [
                'before' => ['assigned_to' => $previous],
                'after' => ['assigned_to' => $assignee?->id],
            ]);
        });

        if ($notify && $assignee && $assignee->id !== $actor?->id) {
            $assignee->notify(new LeadAssigned($lead));
        }

        return $lead;
    }

    public function scheduleFollowUp(Lead $lead, ?CarbonInterface $at, User $actor, ?string $note = null): Lead
    {
        DB::transaction(function () use ($lead, $at, $actor, $note) {
            $before = $lead->next_follow_up_at?->toIso8601String();
            $lead->update(['next_follow_up_at' => $at]);

            $this->record($lead, LeadActivityType::FollowUpScheduled, $note, $actor, [
                'follow_up_at' => $at?->toIso8601String(),
            ]);

            $this->logger->log('prospecto.seguimiento', $lead, [
                'before' => ['next_follow_up_at' => $before],
                'after' => ['next_follow_up_at' => $at?->toIso8601String()],
            ]);
        });

        return $lead;
    }

    private function syncInstance(Lead $original, Lead $fresh): void
    {
        if ($original !== $fresh) {
            $original->setRawAttributes($fresh->getAttributes(), true);
        }
    }
}
