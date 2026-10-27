<?php

namespace App\Services\Operations;

use App\Enums\FabricationStatus;
use App\Enums\InstallationStatus;
use App\Enums\ProjectStatus;
use App\Enums\WarrantyClaimStatus;
use App\Models\FabricationOrder;
use App\Models\Incident;
use App\Models\InstallationOrder;
use App\Models\Project;
use App\Models\User;
use App\Models\WarrantyClaim;
use App\Services\Projects\ProjectWorkflow;

/**
 * Traduce hechos de operación (orden emitida, instalación aprobada,
 * reclamo abierto…) en transiciones del proyecto, siempre a través de
 * ProjectWorkflow. Las transiciones "oportunistas" (avanzar si procede)
 * nunca lanzan: si el proyecto ya está más adelante, no se hace nada.
 */
class ProjectProgress
{
    /** Estados de orden de instalación que cuentan como aprobada. */
    public const APPROVED_INSTALLATION = [
        InstallationStatus::Approved,
        InstallationStatus::PaymentAuthorized,
        InstallationStatus::Paid,
    ];

    public function __construct(private readonly ProjectWorkflow $workflow) {}

    public function workflow(): ProjectWorkflow
    {
        return $this->workflow;
    }

    /** Avanza el proyecto si la transición es válida; devuelve si quedó en `$to`. */
    public function advance(Project $project, ProjectStatus $to, ?User $actor = null, ?string $note = null, bool $notifyClient = true): bool
    {
        $project->refresh();

        if ($project->status === $to) {
            return true;
        }

        if (! $project->status->canTransitionTo($to)) {
            return false;
        }

        $this->workflow->transition($project, $to, $actor, $note, $notifyClient);
        $project->refresh();

        return true;
    }

    /** Todas las órdenes de fabricación vigentes listas/entregadas → proyecto listo para instalar. */
    public function syncAfterFabrication(Project $project, ?User $actor = null): void
    {
        $orders = FabricationOrder::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', FabricationStatus::Cancelled->value)
            ->get(['id', 'status']);

        if ($orders->isEmpty() || $orders->contains(fn (FabricationOrder $o) => ! $o->status->isFinished())) {
            return;
        }

        $this->advance($project, ProjectStatus::ReadyToInstall, $actor, 'Fabricación terminada.');
    }

    /**
     * Cierre del proyecto: todas las instalaciones vigentes aprobadas y sin
     * incidencias abiertas → completado (pasando por revisión de calidad si
     * venía de pendientes).
     */
    public function syncAfterQuality(Project $project, ?User $actor = null): void
    {
        $project->refresh();

        if (! in_array($project->status, [ProjectStatus::QualityReview, ProjectStatus::PunchList], true)) {
            return;
        }

        $orders = InstallationOrder::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', InstallationStatus::Cancelled->value)
            ->get(['id', 'status']);

        $allApproved = $orders->isNotEmpty()
            && $orders->every(fn (InstallationOrder $o) => in_array($o->status, self::APPROVED_INSTALLATION, true));

        if (! $allApproved) {
            return;
        }

        if (Incident::query()->where('project_id', $project->id)->open()->exists()) {
            return;
        }

        $this->advance($project, ProjectStatus::QualityReview, $actor);
        $this->advance($project, ProjectStatus::Completed, $actor, 'Instalación aprobada por calidad.');
    }

    /** Reclamo abierto → atención en garantía; sin reclamos abiertos → completado. */
    public function syncWarranty(Project $project, ?User $actor = null): void
    {
        $hasOpenClaims = WarrantyClaim::query()
            ->whereIn('status', WarrantyClaimStatus::openValues())
            ->whereHas('warranty', fn ($q) => $q->where('project_id', $project->id))
            ->exists();

        $this->advance(
            $project,
            $hasOpenClaims ? ProjectStatus::WarrantyService : ProjectStatus::Completed,
            $actor,
            $hasOpenClaims ? 'Se abrió una solicitud de garantía.' : 'Solicitud de garantía atendida.',
        );
    }
}
