<?php

namespace App\Services\Operations;

use App\Enums\FabricationStatus;
use App\Enums\ProjectStatus;
use App\Models\FabricationOrder;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Órdenes de fabricación. Regla dura: solo con una versión de diseño del
 * mismo proyecto que tenga aprobación técnica (`technical_approved_at`) y
 * con el proyecto autorizado o ya en fabricación.
 */
class FabricationService
{
    /** Estados de proyecto que permiten emitir fabricación. */
    public const PROJECT_STATUSES = [ProjectStatus::Authorized, ProjectStatus::Fabrication];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ProjectProgress $progress,
    ) {}

    /**
     * @param  array{design_version_id: int, supplier_id: int, cost?: string|null, due_on?: string|null, notes?: string|null}  $data
     */
    public function create(Project $project, array $data, User $actor): FabricationOrder
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            /** @var Project $project */
            $project = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! in_array($project->status, self::PROJECT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'project_id' => "El proyecto está en «{$project->status->label()}». Solo se fabrica con el proyecto autorizado (anticipo pagado).",
                ]);
            }

            $design = DB::table('design_versions')
                ->where('id', $data['design_version_id'])
                ->where('project_id', $project->id)
                ->first(['id', 'technical_approved_at']);

            if ($design === null) {
                throw ValidationException::withMessages(['design_version_id' => 'La versión de diseño no pertenece a este proyecto.']);
            }

            if ($design->technical_approved_at === null) {
                throw ValidationException::withMessages([
                    'design_version_id' => 'Esta versión de diseño no tiene aprobación técnica. No se puede fabricar.',
                ]);
            }

            $supplier = $this->fabricator((int) $data['supplier_id']);

            $order = FabricationOrder::query()->create([
                'folio' => Folio::next('F'),
                'project_id' => $project->id,
                'design_version_id' => $design->id,
                'supplier_id' => $supplier->id,
                'status' => FabricationStatus::Draft,
                'cost' => isset($data['cost']) && $data['cost'] !== '' ? Money::of($data['cost'])->amount : null,
                'due_on' => $data['due_on'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->logger->log('fabricacion.creada', $order, ['after' => $order->only(['folio', 'project_id', 'design_version_id', 'supplier_id', 'cost'])]);
            $this->progress->workflow()->record($project, 'fabrication_order', "Orden de fabricación {$order->folio} creada", null, $actor);

            return $order;
        });
    }

    /**
     * @param  array{supplier_id?: int, cost?: string|null, due_on?: string|null, notes?: string|null}  $data
     */
    public function update(FabricationOrder $order, array $data): FabricationOrder
    {
        if (in_array($order->status, [FabricationStatus::Delivered, FabricationStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => 'La orden ya está cerrada.']);
        }

        if (isset($data['supplier_id']) && (int) $data['supplier_id'] !== $order->supplier_id) {
            if ($order->status !== FabricationStatus::Draft) {
                throw ValidationException::withMessages(['supplier_id' => 'Solo se cambia de proveedor mientras la orden es borrador.']);
            }
            $order->supplier_id = $this->fabricator((int) $data['supplier_id'])->id;
        }

        $cost = isset($data['cost']) && $data['cost'] !== '' ? Money::of($data['cost'])->amount : null;
        if ($order->payout()->exists() && $cost !== $order->cost) {
            throw ValidationException::withMessages(['cost' => 'El costo ya tiene un pago solicitado; no se puede cambiar.']);
        }

        $order->fill(['cost' => $cost, 'due_on' => $data['due_on'] ?? null, 'notes' => $data['notes'] ?? null]);
        $this->logger->logModelChanges('fabricacion.actualizada', $order);
        $order->save();

        return $order;
    }

    public function transition(FabricationOrder $order, FabricationStatus $to, User $actor, ?string $note = null): FabricationOrder
    {
        $order = DB::transaction(function () use ($order, $to, $actor, $note) {
            /** @var FabricationOrder $locked */
            $locked = FabricationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de «{$from->label()}» a «{$to->label()}»."]);
            }

            $changes = ['status' => $to];
            match ($to) {
                FabricationStatus::Issued => $changes['issued_at'] = now(),
                FabricationStatus::Ready => $changes['ready_at'] = now(),
                FabricationStatus::Delivered => $changes['delivered_at'] = now(),
                default => null,
            };
            if ($to === FabricationStatus::Delivered && $locked->ready_at === null) {
                $changes['ready_at'] = now();
            }

            if ($to === FabricationStatus::Issued) {
                $project = $locked->project;
                if (! in_array($project->status, self::PROJECT_STATUSES, true)) {
                    throw ValidationException::withMessages(['status' => 'El proyecto ya no está autorizado para fabricación.']);
                }
            }

            $locked->update($changes);

            $this->logger->log('fabricacion.estado', $locked, [
                'before' => ['status' => $from->value],
                'after' => ['status' => $to->value],
                'note' => $note,
            ]);
            $this->progress->workflow()->record($locked->project, 'fabrication_status', "Fabricación {$locked->folio}: {$to->label()}", $note, $actor);

            if ($to === FabricationStatus::Issued) {
                $this->progress->advance($locked->project, ProjectStatus::Fabrication, $actor, "Orden {$locked->folio} emitida al proveedor.");
            }

            return $locked;
        });

        if ($to->isFinished() || $to === FabricationStatus::Cancelled) {
            $this->progress->syncAfterFabrication($order->project, $actor);
        }

        return $order;
    }

    private function fabricator(int $supplierId): Supplier
    {
        $supplier = Supplier::query()->active()->fabricators()->find($supplierId);

        if ($supplier === null) {
            throw ValidationException::withMessages(['supplier_id' => 'Elige un proveedor fabricante activo.']);
        }

        return $supplier;
    }
}
