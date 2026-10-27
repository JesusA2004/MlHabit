<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\User;
use App\Notifications\Projects\ProjectStatusUpdated;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para cambiar el estado de un proyecto: valida la transición,
 * registra el evento en la cronología y en la bitácora de auditoría.
 * Idempotente: pedir el estado actual no hace nada.
 */
class ProjectWorkflow
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CustomerNotifier $notifier,
    ) {}

    public function transition(Project $project, ProjectStatus $to, ?User $actor = null, ?string $note = null, bool $notifyClient = true): Project
    {
        return DB::transaction(function () use ($project, $to, $actor, $note, $notifyClient) {
            /** @var Project $locked */
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de «{$from->label()}» a «{$to->label()}».",
                ]);
            }

            $changes = ['status' => $to];
            if ($to === ProjectStatus::Completed) {
                $changes['completed_at'] = now();
            }
            if ($to === ProjectStatus::Cancelled) {
                $changes['cancelled_at'] = now();
                $changes['cancel_reason'] = $note;
            }
            $locked->update($changes);

            $this->record($locked, 'status_changed', $to->label(), $note, $actor, $notifyClient ? 'client' : 'internal', $from, $to);

            $this->logger->log('proyecto.estado', $locked, [
                'before' => ['status' => $from->value],
                'after' => ['status' => $to->value],
            ]);

            if ($notifyClient) {
                // Tras confirmar la transacción: nunca se avisa de un cambio revertido.
                DB::afterCommit(fn () => $this->notifier->notify(
                    $locked->customer,
                    new ProjectStatusUpdated($locked, $to, $to === ProjectStatus::Cancelled ? null : $note),
                ));
            }

            return $locked;
        });
    }

    /**
     * Agrega un evento a la cronología (sin cambiar estado).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Project $project,
        string $type,
        string $title,
        ?string $body = null,
        ?User $actor = null,
        string $visibility = 'internal',
        ?ProjectStatus $from = null,
        ?ProjectStatus $to = null,
        array $metadata = [],
    ): ProjectEvent {
        return ProjectEvent::query()->create([
            'project_id' => $project->id,
            'user_id' => $actor !== null ? $actor->id : auth()->id(),
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'title' => $title,
            'body' => $body,
            'visibility' => $visibility,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
