<?php

namespace App\Services\Operations;

use App\Enums\IncidentOrigin;
use App\Enums\IncidentStatus;
use App\Enums\ProjectStatus;
use App\Models\Incident;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Operations\IncidentNotification;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Incidencias (folio X). Avisa al responsable asignado y, si es visible
 * para el cliente, también al cliente. Resolver la última incidencia
 * abierta puede cerrar el proyecto (ProjectProgress::syncAfterQuality).
 */
class IncidentService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ProjectProgress $progress,
        private readonly OperationsNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, IncidentOrigin $origin): Incident
    {
        $incident = DB::transaction(function () use ($data, $actor, $origin) {
            $incident = Incident::query()->create([
                ...$this->fillable($data),
                'project_id' => $data['project_id'],
                'installation_order_id' => $data['installation_order_id'] ?? null,
                'fabrication_order_id' => $data['fabrication_order_id'] ?? null,
                'folio' => Folio::next('X'),
                'origin' => $origin,
                'status' => IncidentStatus::Open,
                'reported_by' => $actor->id,
            ]);

            $this->logger->log('incidencia.creada', $incident, ['after' => $incident->only(['folio', 'project_id', 'category', 'severity', 'origin'])]);
            $this->progress->workflow()->record(
                $incident->project, 'incident', "Incidencia {$incident->folio}: {$incident->title}",
                null, $actor, $incident->visible_to_client ? 'client' : 'internal',
                metadata: ['incident_id' => $incident->id],
            );

            return $incident;
        });

        $this->notify($incident, created: true);

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Incident $incident, array $data): Incident
    {
        $previousAssignee = $incident->assigned_to;
        $incident->fill($this->fillable($data));
        $this->logger->logModelChanges('incidencia.actualizada', $incident);
        $incident->save();

        if ($incident->assigned_to !== null && $incident->assigned_to !== $previousAssignee) {
            $this->notifier->send($incident->assignee, new IncidentNotification($incident, 'assigned'));
        }

        return $incident;
    }

    public function transition(Incident $incident, IncidentStatus $to, User $actor, ?string $resolution = null): Incident
    {
        $incident = DB::transaction(function () use ($incident, $to, $actor, $resolution) {
            /** @var Incident $locked */
            $locked = Incident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de «{$from->label()}» a «{$to->label()}»."]);
            }

            $changes = ['status' => $to];
            if ($to === IncidentStatus::Resolved) {
                if (trim((string) ($resolution ?? $locked->resolution)) === '') {
                    throw ValidationException::withMessages(['resolution' => 'Describe cómo se resolvió.']);
                }
                $changes['resolution'] = $resolution ?? $locked->resolution;
                $changes['resolved_at'] = now();
            }
            if ($to === IncidentStatus::InProgress || $to === IncidentStatus::Open) {
                $changes['resolved_at'] = null;
            }

            $locked->update($changes);
            $this->logger->log('incidencia.estado', $locked, ['before' => ['status' => $from->value], 'after' => ['status' => $to->value]]);
            $this->progress->workflow()->record(
                $locked->project, 'incident_status', "Incidencia {$locked->folio}: {$to->label()}",
                $to === IncidentStatus::Resolved ? $locked->resolution : null, $actor,
                $locked->visible_to_client ? 'client' : 'internal',
            );

            return $locked;
        });

        $this->notify($incident, created: false);

        if (! $incident->status->isOpen()) {
            $this->progress->syncAfterQuality($incident->project, $actor);
        }

        return $incident;
    }

    /** Proyecto abierto a incidencias (no cancelado). */
    public function ensureProjectAccepts(Project $project): void
    {
        if ($project->status === ProjectStatus::Cancelled) {
            throw ValidationException::withMessages(['project_id' => 'El proyecto está cancelado.']);
        }
    }

    private function notify(Incident $incident, bool $created): void
    {
        $notification = new IncidentNotification($incident, $created ? 'created' : 'updated');

        if ($incident->assigned_to !== null) {
            $this->notifier->send($incident->assignee, $notification);
        }

        // El cliente que la reportó no necesita aviso de su propio reporte.
        if ($incident->visible_to_client && (! $created || $incident->origin !== IncidentOrigin::Client)) {
            $this->notifier->client($incident->project, $notification);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillable(array $data): array
    {
        $out = collect($data)->only([
            'category', 'severity', 'title', 'description', 'responsible_party', 'responsible_supplier_id',
            'assigned_to', 'action_plan', 'due_on', 'visible_to_client',
        ])->all();

        if (array_key_exists('cost', $data)) {
            $out['cost'] = $data['cost'] === null || $data['cost'] === '' ? null : Money::of($data['cost'])->amount;
        }

        if (($out['responsible_party'] ?? null) !== 'supplier' && array_key_exists('responsible_party', $out)) {
            $out['responsible_supplier_id'] = null;
        }

        return $out;
    }
}
