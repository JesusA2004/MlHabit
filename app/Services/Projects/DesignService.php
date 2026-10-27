<?php

namespace App\Services\Projects;

use App\Enums\DesignStatus;
use App\Models\DesignVersion;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Projects\DesignReadyForReview;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versiones de diseño:
 *   draft → sent_to_client → client_approved | changes_requested
 *   client_approved → technically_approved (permiso designs.approve_technical)
 * Crear una versión nueva marca las anteriores vigentes como `superseded`.
 * Editar una versión ya enviada crea la siguiente versión (con sus archivos).
 */
class DesignService
{
    public const FILE_ROLES = ['render', 'file'];

    public function __construct(
        private readonly ProjectWorkflow $workflow,
        private readonly ActivityLogger $logger,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * @param  array{title: string, notes?: string|null, measurement_id?: int|null}  $data
     */
    public function create(Project $project, array $data, User $actor, ?DesignVersion $from = null): DesignVersion
    {
        $this->assertMeasurementBelongs($project, $data['measurement_id'] ?? null);

        return DB::transaction(function () use ($project, $data, $actor, $from) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $number = (int) DesignVersion::query()->where('project_id', $project->id)->max('number') + 1;

            DesignVersion::query()
                ->where('project_id', $project->id)
                ->where('status', '!=', DesignStatus::Superseded)
                ->update(['status' => DesignStatus::Superseded]);

            /** @var DesignVersion $version */
            $version = DesignVersion::query()->create([
                'project_id' => $project->id,
                'number' => $number,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'measurement_id' => $data['measurement_id'] ?? $from->measurement_id ?? $this->latestMeasurementId($project),
                'status' => DesignStatus::Draft,
                'created_by' => $actor->id,
            ]);

            if ($from) {
                foreach ($from->attachments()->whereIn('role', self::FILE_ROLES)->get() as $attachment) {
                    $version->attachments()->create([
                        'media_id' => $attachment->media_id,
                        'role' => $attachment->role,
                        'caption' => $attachment->caption,
                        'sort' => $attachment->sort,
                        'attached_by' => $attachment->attached_by,
                    ]);
                }
            }

            $this->workflow->record($project, 'design_version_created', "Diseño v{$number} creado", $from ? "Basado en la v{$from->number}" : null, $actor, metadata: ['design_version_id' => $version->id]);
            $this->logger->log('diseno.creado', $version, ['number' => $number, 'from' => $from?->id]);

            return $version;
        });
    }

    /**
     * Edita en sitio si es borrador; si ya se envió, crea la siguiente versión.
     *
     * @param  array{title: string, notes?: string|null, measurement_id?: int|null}  $data
     */
    public function update(DesignVersion $version, array $data, User $actor): DesignVersion
    {
        $this->assertNotSuperseded($version);

        if ($version->isEditableInPlace()) {
            $this->assertMeasurementBelongs($version->project, $data['measurement_id'] ?? null);
            $version->fill([
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'measurement_id' => $data['measurement_id'] ?? $version->measurement_id,
            ]);
            $this->logger->logModelChanges('diseno.actualizado', $version);
            $version->save();

            return $version;
        }

        return $this->create($version->project, $data, $actor, $version);
    }

    public function send(DesignVersion $version, User $actor): DesignVersion
    {
        $version = DB::transaction(function () use ($version, $actor) {
            /** @var DesignVersion $locked */
            $locked = DesignVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === DesignStatus::SentToClient) {
                return $locked;
            }
            if ($locked->status !== DesignStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Solo se envía al cliente una versión en borrador.']);
            }
            if (! $locked->attachments()->whereIn('role', self::FILE_ROLES)->exists()) {
                throw ValidationException::withMessages(['files' => 'Sube al menos un render o archivo antes de enviarlo al cliente.']);
            }

            $locked->update(['status' => DesignStatus::SentToClient, 'sent_at' => now()]);

            $project = $locked->project;
            $this->workflow->record($project, 'design_sent', "Tu diseño v{$locked->number} está listo para revisión", 'Revísalo y apruébalo o pide cambios desde tu portal.', $actor, 'client', metadata: ['design_version_id' => $locked->id]);
            $this->logger->log('diseno.enviado', $locked, ['number' => $locked->number]);

            return $locked;
        });

        $this->notifier->notify($version->project->customer, new DesignReadyForReview($version));

        return $version;
    }

    /**
     * Decisión del cliente desde el portal. Aprobar exige marcar distribución
     * y acabados; pedir cambios exige comentario.
     */
    public function clientDecision(DesignVersion $version, User $client, bool $approve, bool $layout, bool $finishes, ?string $comment): DesignVersion
    {
        return DB::transaction(function () use ($version, $client, $approve, $layout, $finishes, $comment) {
            /** @var DesignVersion $locked */
            $locked = DesignVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== DesignStatus::SentToClient) {
                throw ValidationException::withMessages(['status' => 'Esta versión del diseño ya no espera tu revisión.']);
            }
            if ($approve && (! $layout || ! $finishes)) {
                throw ValidationException::withMessages(['layout' => 'Para aprobar, confirma la distribución y los acabados.']);
            }
            if (! $approve && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Cuéntanos qué te gustaría cambiar.']);
            }

            $locked->update([
                'status' => $approve ? DesignStatus::ClientApproved : DesignStatus::ChangesRequested,
                'client_approved_layout' => $approve ? true : $layout,
                'client_approved_finishes' => $approve ? true : $finishes,
                'client_decided_at' => now(),
                'client_decided_by' => $client->id,
                'client_comment' => $comment,
            ]);

            $title = $approve ? "Aprobaste el diseño v{$locked->number}" : "Pediste cambios al diseño v{$locked->number}";
            $this->workflow->record($locked->project, $approve ? 'design_client_approved' : 'design_changes_requested', $title, $comment, $client, 'client', metadata: ['design_version_id' => $locked->id]);
            $this->logger->log($approve ? 'diseno.aprobado_cliente' : 'diseno.cambios_solicitados', $locked, ['comment' => $comment]);

            return $locked;
        });
    }

    /** Aprobación técnica interna (requisito para fabricar). */
    public function approveTechnical(DesignVersion $version, User $actor, ?string $notes): DesignVersion
    {
        if (! $actor->hasPermission('designs.approve_technical')) {
            throw ValidationException::withMessages(['status' => 'No tienes permiso para aprobar diseños técnicamente.']);
        }

        return DB::transaction(function () use ($version, $actor, $notes) {
            /** @var DesignVersion $locked */
            $locked = DesignVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($locked->technical_approved_at !== null) {
                return $locked;
            }
            if ($locked->status !== DesignStatus::ClientApproved) {
                throw ValidationException::withMessages(['status' => 'La aprobación técnica requiere que el cliente haya aprobado esta versión.']);
            }

            $locked->update([
                'status' => DesignStatus::TechnicallyApproved,
                'technical_approved_at' => now(),
                'technical_approved_by' => $actor->id,
                'technical_notes' => $notes,
            ]);

            $this->workflow->record($locked->project, 'design_technical_approved', "Diseño v{$locked->number} aprobado técnicamente", $notes, $actor, metadata: ['design_version_id' => $locked->id]);
            $this->logger->log('diseno.aprobado_tecnico', $locked, ['notes' => $notes]);

            return $locked;
        });
    }

    public function attachFile(DesignVersion $version, int $mediaId, string $role, ?string $caption, User $actor): void
    {
        $this->assertNotSuperseded($version);
        if (! $version->isEditableInPlace()) {
            throw ValidationException::withMessages(['media_id' => 'Esta versión ya se envió. Crea una nueva versión para cambiar sus archivos.']);
        }
        $version->attachMedia($mediaId, $role, $caption);
        $this->logger->log('diseno.archivo_agregado', $version, ['media_id' => $mediaId, 'role' => $role, 'by' => $actor->id]);
    }

    public function detachFile(DesignVersion $version, int $attachmentId, User $actor): void
    {
        if (! $version->isEditableInPlace()) {
            throw ValidationException::withMessages(['media_id' => 'Esta versión ya se envió: sus archivos no se pueden quitar.']);
        }
        $version->attachments()->whereKey($attachmentId)->delete();
        $this->logger->log('diseno.archivo_quitado', $version, ['attachment_id' => $attachmentId, 'by' => $actor->id]);
    }

    private function assertNotSuperseded(DesignVersion $version): void
    {
        if ($version->status === DesignStatus::Superseded) {
            throw ValidationException::withMessages(['status' => 'Esta versión fue reemplazada por una más reciente.']);
        }
    }

    private function assertMeasurementBelongs(Project $project, ?int $measurementId): void
    {
        if ($measurementId !== null && ! Measurement::query()->whereKey($measurementId)->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['measurement_id' => 'La medición no pertenece a este proyecto.']);
        }
    }

    private function latestMeasurementId(Project $project): ?int
    {
        $id = Measurement::query()->where('project_id', $project->id)->where('status', 'submitted')->latest('version')->value('id');

        return $id === null ? null : (int) $id;
    }
}
