<?php

namespace App\Services\Operations;

use App\Enums\ProjectStatus;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Notifications\Operations\WarrantyRegistered;
use App\Services\ActivityLogger;
use App\Support\Folio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garantías y reclamos. Ningún término (vigencia, alcance, exclusiones)
 * está fijo en código: el equipo los captura por tipo.
 */
class WarrantyService
{
    public const REGISTRABLE_PROJECT_STATUSES = [ProjectStatus::Completed, ProjectStatus::WarrantyService];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ProjectProgress $progress,
        private readonly OperationsNotifier $notifier,
    ) {}

    /**
     * @param  list<array{type: string, supplier_id?: int|null, starts_on: string, ends_on: string, scope: string, exclusions?: string|null}>  $entries
     * @return list<Warranty>
     */
    public function register(Project $project, array $entries, User $actor): array
    {
        if (! in_array($project->status, self::REGISTRABLE_PROJECT_STATUSES, true)) {
            throw ValidationException::withMessages(['project_id' => 'Las garantías se registran cuando el proyecto está completado.']);
        }

        $created = DB::transaction(function () use ($project, $entries, $actor) {
            $out = [];
            foreach ($entries as $entry) {
                $warranty = Warranty::query()->create([
                    'folio' => Folio::next('W'),
                    'project_id' => $project->id,
                    'type' => $entry['type'],
                    'supplier_id' => $entry['supplier_id'] ?? null,
                    'starts_on' => $entry['starts_on'],
                    'ends_on' => $entry['ends_on'],
                    'scope' => $entry['scope'],
                    'exclusions' => $entry['exclusions'] ?? null,
                    'status' => WarrantyStatus::Active,
                ]);
                $this->logger->log('garantia.registrada', $warranty, ['after' => $warranty->only(['folio', 'type', 'starts_on', 'ends_on'])]);
                $out[] = $warranty;
            }

            $this->progress->workflow()->record($project, 'warranty', count($out) === 1 ? 'Garantía registrada' : 'Garantías registradas', null, $actor, 'client');

            return $out;
        });

        $this->notifier->client($project, new WarrantyRegistered($project, $created));

        return $created;
    }

    /**
     * @param  array{supplier_id?: int|null, starts_on: string, ends_on: string, scope: string, exclusions?: string|null}  $data
     */
    public function update(Warranty $warranty, array $data): Warranty
    {
        if ($warranty->status !== WarrantyStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Solo se editan garantías vigentes.']);
        }

        $warranty->fill($data);
        $this->logger->logModelChanges('garantia.actualizada', $warranty);
        $warranty->save();

        return $warranty;
    }

    public function void(Warranty $warranty, string $reason): Warranty
    {
        if ($warranty->status === WarrantyStatus::Voided) {
            return $warranty;
        }

        $before = $warranty->status;
        $warranty->update(['status' => WarrantyStatus::Voided]);
        $this->logger->log('garantia.anulada', $warranty, ['before' => ['status' => $before->value], 'reason' => $reason]);

        return $warranty;
    }

    /** Marca como vencidas las garantías activas cuya vigencia terminó. */
    public function expireDue(): int
    {
        $due = Warranty::query()
            ->where('status', WarrantyStatus::Active->value)
            ->whereDate('ends_on', '<', now()->toDateString())
            ->get();

        foreach ($due as $warranty) {
            $warranty->update(['status' => WarrantyStatus::Expired]);
            $this->logger->log('garantia.vencida', $warranty, ['ends_on' => $warranty->ends_on->toDateString()]);
        }

        return $due->count();
    }

    /**
     * @param  array{description: string, incident_id?: int|null}  $data
     */
    public function openClaim(Warranty $warranty, array $data, User $actor): WarrantyClaim
    {
        if (! $warranty->isInForce()) {
            throw ValidationException::withMessages(['warranty_id' => 'La garantía no está vigente.']);
        }

        $claim = DB::transaction(function () use ($warranty, $data, $actor) {
            $claim = $warranty->claims()->create([
                'folio' => Folio::next('R'),
                'incident_id' => $data['incident_id'] ?? null,
                'description' => $data['description'],
                'status' => WarrantyClaimStatus::Open,
                'reported_by' => $actor->id,
            ]);

            $this->logger->log('garantia.reclamo_abierto', $claim, ['warranty' => $warranty->folio]);
            $this->progress->workflow()->record($warranty->project, 'warranty_claim', "Solicitud de garantía {$claim->folio}", null, $actor, 'client');

            return $claim;
        });

        $this->progress->syncWarranty($warranty->project, $actor);

        return $claim;
    }

    public function updateClaim(WarrantyClaim $claim, WarrantyClaimStatus $status, ?string $resolution, User $actor): WarrantyClaim
    {
        if (! $status->isOpen() && trim((string) $resolution) === '') {
            throw ValidationException::withMessages(['resolution' => 'Describe la resolución del reclamo.']);
        }

        $before = $claim->status;
        $claim->update([
            'status' => $status,
            'resolution' => $resolution,
            'resolved_at' => $status->isOpen() ? null : now(),
        ]);

        $this->logger->log('garantia.reclamo_actualizado', $claim, ['before' => ['status' => $before->value], 'after' => ['status' => $status->value]]);

        if ($before->isOpen() !== $status->isOpen()) {
            $this->progress->workflow()->record(
                $claim->warranty->project, 'warranty_claim', "Solicitud de garantía {$claim->folio}: {$status->label()}",
                $status->isOpen() ? null : $resolution, $actor, 'client',
            );
        }

        $this->progress->syncWarranty($claim->warranty->project, $actor);

        return $claim;
    }
}
