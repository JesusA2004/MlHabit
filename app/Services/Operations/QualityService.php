<?php

namespace App\Services\Operations;

use App\Enums\IncidentOrigin;
use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\QualityResult;
use App\Enums\QualityStage;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Models\QualityInspection;
use App\Models\User;
use App\Notifications\Operations\InstallationQualityResult;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revisión de calidad: dictamen por partida (checklist etapa `quality`) y
 * dictamen de la orden. Aprobar puede completar el proyecto; requerir
 * correcciones abre una lista de pendientes (punch list) con incidencias.
 */
class QualityService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ProjectProgress $progress,
        private readonly InstallationService $installations,
        private readonly IncidentService $incidents,
        private readonly OperationsNotifier $notifier,
    ) {}

    /**
     * @param  list<array{criterion: string, result: string, notes?: string|null}>  $checklist
     */
    public function reviewItem(InstallationOrder $order, InstallationOrderItem $item, User $reviewer, QualityResult $result, array $checklist, ?string $observations, bool $clientContacted): QualityInspection
    {
        $this->ensureInReview($order);

        if ($result === QualityResult::Pending) {
            throw ValidationException::withMessages(['result' => 'Elige un dictamen para la partida.']);
        }

        if ($result === QualityResult::RequiresFixes && trim((string) $observations) === '') {
            throw ValidationException::withMessages(['observations' => 'Describe qué hay que corregir.']);
        }

        return DB::transaction(function () use ($order, $item, $reviewer, $result, $checklist, $observations, $clientContacted) {
            $inspection = $this->installations->saveChecklist($order, $item, QualityStage::Quality, $reviewer, $checklist, $observations, $result, $clientContacted);
            $this->logger->log('calidad.partida', $order, ['item' => $item->id, 'result' => $result->value]);

            return $inspection;
        });
    }

    /** Aprueba la orden: todas las partidas vigentes deben estar aprobadas por calidad. */
    public function approve(InstallationOrder $order, User $reviewer, ?string $observations, bool $clientContacted): InstallationOrder
    {
        $order = DB::transaction(function () use ($order, $reviewer, $observations, $clientContacted) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->ensureInReview($locked);

            $items = $locked->items()->with(['inspections' => fn ($q) => $q->where('stage', QualityStage::Quality->value)])->get()
                ->filter(fn (InstallationOrderItem $i) => $i->countsTowardsTotal());

            $missing = $items->filter(fn (InstallationOrderItem $i) => $i->inspections->first()?->result !== QualityResult::Approved);
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Revisa y aprueba cada partida antes de aprobar la orden. Pendientes: '.$missing->pluck('code')->implode(', ').'.',
                ]);
            }

            QualityInspection::query()->updateOrCreate(
                ['installation_order_id' => $locked->id, 'installation_order_item_id' => null, 'stage' => QualityStage::Quality->value],
                ['result' => QualityResult::Approved, 'observations' => $observations, 'client_contacted' => $clientContacted, 'inspector_id' => $reviewer->id, 'inspected_at' => now()],
            );

            $locked->update(['status' => InstallationStatus::Approved, 'completed_at' => now()]);
            $this->logger->log('calidad.aprobada', $locked, ['by' => $reviewer->id]);
            $this->progress->workflow()->record($locked->project, 'quality_approved', "Calidad aprobó la instalación {$locked->folio}", $observations, $reviewer, 'client');

            return $locked;
        });

        $this->progress->syncAfterQuality($order->project, $reviewer);
        $this->notifier->send([$order->leadInstaller, $order->project->operationsOwner], new InstallationQualityResult($order, true, $observations));

        return $order;
    }

    /**
     * Requiere correcciones: la orden vuelve a ejecución, las partidas
     * observadas se reabren y se crean incidencias (origen calidad).
     *
     * @param  list<array{title: string, description: string, category: string, severity: string, item_id?: int|null, visible_to_client?: bool}>  $issues
     */
    public function requireFixes(InstallationOrder $order, User $reviewer, string $observations, array $issues, bool $clientContacted): InstallationOrder
    {
        $order = DB::transaction(function () use ($order, $reviewer, $observations, $issues, $clientContacted) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->ensureInReview($locked);

            QualityInspection::query()->updateOrCreate(
                ['installation_order_id' => $locked->id, 'installation_order_item_id' => null, 'stage' => QualityStage::Quality->value],
                ['result' => QualityResult::RequiresFixes, 'observations' => $observations, 'client_contacted' => $clientContacted, 'inspector_id' => $reviewer->id, 'inspected_at' => now()],
            );

            // Partidas observadas por calidad (o señaladas en los pendientes) vuelven a pendiente.
            $flagged = $locked->items()
                ->whereHas('inspections', fn ($q) => $q->where('stage', QualityStage::Quality->value)->where('result', QualityResult::RequiresFixes->value))
                ->pluck('id')
                ->merge(collect($issues)->pluck('item_id')->filter())
                ->unique();

            $locked->items()->whereIn('id', $flagged)->update(['status' => InstallationItemStatus::Pending->value]);

            foreach ($issues as $issue) {
                $this->incidents->create([
                    'project_id' => $locked->project_id,
                    'installation_order_id' => $locked->id,
                    'category' => $issue['category'],
                    'severity' => $issue['severity'],
                    'title' => $issue['title'],
                    'description' => $issue['description'],
                    'visible_to_client' => (bool) ($issue['visible_to_client'] ?? true),
                    'responsible_party' => 'supplier',
                    'responsible_supplier_id' => $locked->supplier_id,
                    'assigned_to' => $locked->lead_installer_id,
                ], $reviewer, IncidentOrigin::Quality);
            }

            $locked->update(['status' => InstallationStatus::InProgress]);
            $this->logger->log('calidad.correcciones', $locked, ['by' => $reviewer->id, 'items' => $flagged->values()->all(), 'issues' => count($issues)]);

            $this->progress->advance($locked->project, ProjectStatus::PunchList, $reviewer, 'Calidad detectó detalles por corregir.');

            return $locked;
        });

        $this->notifier->send([$order->leadInstaller, $order->project->operationsOwner], new InstallationQualityResult($order, false, $observations));

        return $order;
    }

    private function ensureInReview(InstallationOrder $order): void
    {
        if ($order->status !== InstallationStatus::QualityReview) {
            throw ValidationException::withMessages(['status' => 'La orden no está en revisión de calidad.']);
        }
    }
}
