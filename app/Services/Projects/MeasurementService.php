<?php

namespace App\Services\Projects;

use App\Enums\MeasurementStatus;
use App\Models\Measurement;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mediciones versionadas. Reglas:
 *  - Solo un borrador abierto por visita.
 *  - Una versión enviada (`submitted`) es inmutable.
 *  - Corregir una versión enviada crea la siguiente versión (copia de
 *    renglones) con `supersedes_id`; al enviarse, la anterior queda
 *    `superseded`. Nunca se sobrescribe ni se borra lo enviado.
 *  - Versión consecutiva por proyecto (UNIQUE project_id+version); si la
 *    visita aún no tiene proyecto, consecutiva por visita y se renumera al
 *    vincularla (ProjectService::linkVisit).
 */
class MeasurementService
{
    public function __construct(
        private readonly ProjectWorkflow $workflow,
        private readonly ActivityLogger $logger,
    ) {}

    /** Borrador abierto de la visita (lo crea si no hay). */
    public function draftForVisit(SiteVisit $visit, User $technician): Measurement
    {
        return DB::transaction(function () use ($visit, $technician) {
            /** @var SiteVisit $locked */
            $locked = SiteVisit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            $draft = Measurement::query()
                ->where('site_visit_id', $locked->id)
                ->where('status', MeasurementStatus::Draft)
                ->latest('id')
                ->first();
            if ($draft) {
                return $draft;
            }

            $previous = Measurement::query()
                ->where('site_visit_id', $locked->id)
                ->where('status', MeasurementStatus::Submitted)
                ->latest('version')
                ->first();

            return $previous
                ? $this->copyAsDraft($previous, $technician)
                : Measurement::query()->create([
                    'site_visit_id' => $locked->id,
                    'project_id' => $locked->project_id,
                    'technician_id' => $technician->id,
                    'version' => $this->nextVersion($locked->project_id, $locked->id),
                    'status' => MeasurementStatus::Draft,
                    'access_notes' => $locked->access_notes,
                    'recorded_at' => now(),
                ]);
        });
    }

    /** Nueva versión editable a partir de una enviada (corrección). */
    public function revise(Measurement $submitted, User $actor): Measurement
    {
        if ($submitted->status !== MeasurementStatus::Submitted) {
            throw ValidationException::withMessages(['measurement' => 'Solo se puede corregir una medición enviada.']);
        }

        return DB::transaction(function () use ($submitted, $actor) {
            if ($submitted->site_visit_id) {
                SiteVisit::query()->whereKey($submitted->site_visit_id)->lockForUpdate()->first();
            }
            if ($submitted->project_id) {
                Project::query()->whereKey($submitted->project_id)->lockForUpdate()->first();
            }

            $open = Measurement::query()->where('supersedes_id', $submitted->id)->where('status', MeasurementStatus::Draft)->first();

            return $open ?? $this->copyAsDraft($submitted, $actor);
        });
    }

    /**
     * Guarda el borrador (encabezado + renglones completos).
     *
     * @param  array{space_type?: string|null, access_notes?: string|null, existing_installations?: string|null, general_notes?: string|null, items?: list<array{area: string, kind: string, label?: string|null, value_mm?: int|null, notes?: string|null}>}  $data
     */
    public function saveDraft(Measurement $measurement, array $data): Measurement
    {
        return DB::transaction(function () use ($measurement, $data) {
            /** @var Measurement $locked */
            $locked = Measurement::query()->whereKey($measurement->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['measurement' => 'Esta medición ya se envió. Crea una corrección para cambiarla.']);
            }

            $locked->update([
                'space_type' => $data['space_type'] ?? $locked->space_type,
                'access_notes' => $data['access_notes'] ?? null,
                'existing_installations' => $data['existing_installations'] ?? null,
                'general_notes' => $data['general_notes'] ?? null,
                'recorded_at' => $locked->recorded_at ?? now(),
            ]);

            if (array_key_exists('items', $data)) {
                $locked->items()->delete();
                foreach ($data['items'] as $index => $item) {
                    $locked->items()->create([
                        'area' => mb_substr(trim($item['area']), 0, 80),
                        'kind' => $item['kind'],
                        'label' => $item['label'] ?? null,
                        'value_mm' => isset($item['value_mm']) ? (int) $item['value_mm'] : null,
                        'notes' => $item['notes'] ?? null,
                        'sort' => $index,
                    ]);
                }
            }

            return $locked->refresh();
        });
    }

    public function submit(Measurement $measurement, User $actor): Measurement
    {
        return DB::transaction(function () use ($measurement, $actor) {
            /** @var Measurement $locked */
            $locked = Measurement::query()->whereKey($measurement->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MeasurementStatus::Submitted) {
                return $locked;
            }
            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['measurement' => 'Esta versión ya no se puede enviar.']);
            }
            if (! $locked->items()->whereNotNull('value_mm')->exists()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos una medida con valor antes de enviar.']);
            }

            $locked->update(['status' => MeasurementStatus::Submitted, 'submitted_at' => now()]);

            if ($locked->supersedes_id) {
                Measurement::query()->whereKey($locked->supersedes_id)
                    ->where('status', MeasurementStatus::Submitted)
                    ->update(['status' => MeasurementStatus::Superseded]);
            }

            if ($locked->project_id) {
                /** @var Project $project */
                $project = Project::query()->findOrFail($locked->project_id);
                $this->workflow->record($project, 'measurement_submitted', "Medición v{$locked->version} registrada", null, $actor, 'internal', metadata: ['measurement_id' => $locked->id]);
                $this->workflow->record($project, 'measurement_done', 'Tomamos las medidas de tu espacio', 'Con ellas preparamos tu diseño.', $actor, 'client');
            }

            $this->logger->log('medicion.enviada', $locked, ['version' => $locked->version]);

            return $locked;
        });
    }

    private function copyAsDraft(Measurement $source, User $actor): Measurement
    {
        /** @var Measurement $draft */
        $draft = Measurement::query()->create([
            'site_visit_id' => $source->site_visit_id,
            'project_id' => $source->project_id,
            'technician_id' => $actor->id,
            'version' => $this->nextVersion($source->project_id, $source->site_visit_id),
            'status' => MeasurementStatus::Draft,
            'space_type' => $source->space_type,
            'access_notes' => $source->access_notes,
            'existing_installations' => $source->existing_installations,
            'general_notes' => $source->general_notes,
            'recorded_at' => now(),
            'supersedes_id' => $source->id,
        ]);

        foreach ($source->items as $item) {
            $draft->items()->create($item->only(['area', 'kind', 'label', 'value_mm', 'notes', 'sort']));
        }

        foreach ($source->attachments()->get() as $attachment) {
            $draft->attachments()->firstOrCreate(
                ['media_id' => $attachment->media_id, 'role' => $attachment->role],
                ['caption' => $attachment->caption, 'sort' => $attachment->sort, 'attached_by' => $attachment->attached_by],
            );
        }

        return $draft;
    }

    private function nextVersion(?int $projectId, ?int $visitId): int
    {
        $query = Measurement::query();
        $projectId
            ? $query->where('project_id', $projectId)
            : $query->whereNull('project_id')->where('site_visit_id', $visitId);

        return (int) $query->max('version') + 1;
    }
}
