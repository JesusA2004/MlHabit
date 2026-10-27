<?php

namespace App\Services\Operations;

use App\Enums\CheckResult;
use App\Enums\ClientConformity;
use App\Enums\IncidentCategory;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentSeverity;
use App\Enums\InstallationItemStatus;
use App\Enums\InstallationStatus;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\QualityCriterion;
use App\Enums\QualityResult;
use App\Enums\QualityStage;
use App\Enums\RateCardStatus;
use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Models\Project;
use App\Models\QualityInspection;
use App\Models\RateCard;
use App\Models\RateCardItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Operations\InstallationEvidenceReady;
use App\Notifications\Operations\InstallationScheduled;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de la orden de instalación: alta con tarifario VIGENTE (precios
 * congelados), extras con aprobación de un tercero, programación,
 * ejecución con evidencia y autoevaluación, y conformidad del cliente.
 *
 * Fórmula de totales (siempre en backend, con Money):
 *   subtotal = Σ line_total de partidas normales + extras APROBADOS
 *   total    = max(subtotal + movilización, cargo mínimo)
 */
class InstallationService
{
    /** Estados de proyecto en los que se puede crear una orden. */
    public const CREATABLE_PROJECT_STATUSES = [
        ProjectStatus::Fabrication, ProjectStatus::ReadyToInstall, ProjectStatus::InstallationScheduled,
        ProjectStatus::Installing, ProjectStatus::QualityReview, ProjectStatus::PunchList,
    ];

    /** Estados de proyecto en los que se puede programar. */
    public const SCHEDULABLE_PROJECT_STATUSES = [
        ProjectStatus::ReadyToInstall, ProjectStatus::InstallationScheduled, ProjectStatus::Installing,
        ProjectStatus::QualityReview, ProjectStatus::PunchList,
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ProjectProgress $progress,
        private readonly Attachments $attachments,
        private readonly IncidentService $incidents,
        private readonly OperationsNotifier $notifier,
    ) {}

    // ------------------------------------------------------------------ Alta

    /**
     * @param  array{supplier_id: int, rate_card_id: int, lead_installer_id?: int|null, notes?: string|null, items: list<array{rate_card_item_id: int, quantity: string}>}  $data
     */
    public function create(Project $project, array $data, User $actor): InstallationOrder
    {
        return DB::transaction(function () use ($project, $data, $actor) {
            if (! in_array($project->status, self::CREATABLE_PROJECT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'project_id' => "El proyecto está en «{$project->status->label()}»; aún no se puede planear su instalación.",
                ]);
            }

            /** @var RateCard|null $card */
            $card = RateCard::query()->whereKey($data['rate_card_id'])->lockForUpdate()->first();
            if ($card === null || $card->status !== RateCardStatus::Active) {
                throw ValidationException::withMessages(['rate_card_id' => 'Elige un tarifario vigente.']);
            }

            $supplier = Supplier::query()->active()->installers()->find($data['supplier_id']);
            if ($supplier === null) {
                throw ValidationException::withMessages(['supplier_id' => 'Elige un proveedor instalador activo.']);
            }

            if ($card->supplier_id !== null && $card->supplier_id !== $supplier->id) {
                throw ValidationException::withMessages(['rate_card_id' => 'Este tarifario pertenece a otro proveedor.']);
            }

            $leadId = isset($data['lead_installer_id']) ? $this->installer((int) $data['lead_installer_id'])->id : null;

            $order = InstallationOrder::query()->create([
                'folio' => Folio::next('I'),
                'project_id' => $project->id,
                'supplier_id' => $supplier->id,
                'rate_card_id' => $card->id,
                'lead_installer_id' => $leadId,
                'status' => InstallationStatus::Draft,
                // Congelados: cambios futuros del tarifario no afectan esta orden.
                'mobilization_fee' => Money::of($card->mobilization_fee)->amount,
                'minimum_charge' => Money::of($card->minimum_charge)->amount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->addRateCardItems($order, $card, $data['items']);
            $this->recalculate($order);

            $this->logger->log('instalacion.creada', $order, ['after' => $order->only(['folio', 'project_id', 'supplier_id', 'rate_card_id', 'lead_installer_id', 'total'])]);
            $this->progress->workflow()->record($project, 'installation_order', "Orden de instalación {$order->folio} creada", null, $actor);

            return $order;
        });
    }

    /**
     * @param  array{lead_installer_id?: int|null, notes?: string|null}  $data
     */
    public function update(InstallationOrder $order, array $data): InstallationOrder
    {
        $this->ensureNotClosed($order);

        $leadId = isset($data['lead_installer_id']) ? $this->installer((int) $data['lead_installer_id'])->id : null;
        if ($leadId !== $order->lead_installer_id && ! in_array($order->status, [InstallationStatus::Draft, InstallationStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['lead_installer_id' => 'El instalador líder solo cambia antes de iniciar la instalación.']);
        }

        $order->fill(['lead_installer_id' => $leadId, 'notes' => $data['notes'] ?? null]);
        $this->logger->logModelChanges('instalacion.actualizada', $order);
        $order->save();

        return $order;
    }

    /**
     * @param  list<array{rate_card_item_id: int, quantity: string}>  $items
     */
    public function addItems(InstallationOrder $order, array $items): void
    {
        DB::transaction(function () use ($order, $items) {
            $this->ensureEditable($order);
            $this->addRateCardItems($order, $order->rateCard, $items);
            $this->recalculate($order);
            $this->logger->log('instalacion.partidas_agregadas', $order, ['items' => $items]);
        });
    }

    public function updateQuantity(InstallationOrderItem $item, string $quantity): void
    {
        DB::transaction(function () use ($item, $quantity) {
            $order = $item->order;
            $this->ensureEditable($order);
            $before = $item->quantity;
            $item->update([
                'quantity' => $quantity,
                'line_total' => Money::of($item->unit_price)->times($quantity)->amount,
            ]);
            $this->recalculate($order);
            $this->logger->log('instalacion.cantidad', $order, ['item' => $item->id, 'before' => $before, 'after' => $quantity]);
        });
    }

    public function removeItem(InstallationOrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            $order = $item->order;
            $this->ensureEditable($order);
            $this->logger->log('instalacion.partida_eliminada', $order, ['item' => $item->only(['code', 'quantity', 'line_total'])]);
            $item->delete();
            $this->recalculate($order);
        });
    }

    // ------------------------------------------------------------------ Extras

    /**
     * Propuesta de trabajo extra (por personal o por el instalador líder en
     * sitio). No suma al total hasta que se aprueba.
     *
     * @param  array<string, mixed>  $data  Validado en el controlador (description, unit, quantity, unit_price|rate_card_item_id)
     */
    public function addExtra(InstallationOrder $order, array $data, User $actor): InstallationOrderItem
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            if (in_array($order->status, [InstallationStatus::Approved, InstallationStatus::PaymentAuthorized, InstallationStatus::Paid, InstallationStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'La orden ya no admite trabajos extra.']);
            }

            $code = $data['code'] ?? 'EXTRA';
            $unitPrice = $data['unit_price'] ?? null;
            $rateItemId = null;

            if (! empty($data['rate_card_item_id'])) {
                /** @var RateCardItem|null $rateItem */
                $rateItem = $order->rateCard->items()->whereKey($data['rate_card_item_id'])->first();
                if ($rateItem === null) {
                    throw ValidationException::withMessages(['rate_card_item_id' => 'El concepto no pertenece al tarifario de la orden.']);
                }
                $rateItemId = $rateItem->id;
                $code = $rateItem->code;
                $unitPrice = $rateItem->unit_price;
            }

            if ($unitPrice === null || $unitPrice === '') {
                throw ValidationException::withMessages(['unit_price' => 'Indica el precio unitario del extra.']);
            }

            $item = $order->items()->create([
                'rate_card_item_id' => $rateItemId,
                'code' => mb_strtoupper((string) $code),
                'description' => $data['description'],
                'unit' => $data['unit'],
                'quantity' => $data['quantity'],
                'unit_price' => Money::of($unitPrice)->amount,
                'line_total' => Money::of($unitPrice)->times($data['quantity'])->amount,
                'is_extra' => true,
                'status' => InstallationItemStatus::Pending,
                'sort' => (int) $order->items()->max('sort') + 1,
            ]);

            $this->recalculate($order);
            $this->logger->log('instalacion.extra_propuesto', $order, ['item' => $item->id, 'by' => $actor->id, 'line_total' => $item->line_total]);

            return $item;
        });
    }

    /**
     * Aprobar/rechazar un extra: requiere installations.manage y NO ser el
     * instalador líder de la orden (nadie aprueba su propio cobro).
     */
    public function decideExtra(InstallationOrderItem $item, User $actor, bool $approve): InstallationOrderItem
    {
        return DB::transaction(function () use ($item, $actor, $approve) {
            /** @var InstallationOrderItem $locked */
            $locked = InstallationOrderItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $order = $locked->order;

            if (! $actor->hasPermission(Permission::InstallationsManage) || $order->isLedBy($actor)) {
                throw new AuthorizationException('El instalador líder no puede aprobar sus propios extras.');
            }

            if (! $locked->is_extra) {
                throw ValidationException::withMessages(['item' => 'La partida no es un extra.']);
            }

            if (! $locked->isPendingExtra()) {
                return $locked;
            }

            $locked->update($approve
                ? ['extra_approved_by' => $actor->id, 'extra_approved_at' => now()]
                : ['status' => InstallationItemStatus::Rejected]);

            $this->recalculate($order);
            $this->logger->log($approve ? 'instalacion.extra_aprobado' : 'instalacion.extra_rechazado', $order, [
                'item' => $locked->id,
                'line_total' => $locked->line_total,
                'total' => $order->total,
            ]);

            return $locked;
        });
    }

    // ------------------------------------------------------------------ Totales

    public function recalculate(InstallationOrder $order): InstallationOrder
    {
        $items = $order->items()->get();
        [$subtotal, $total] = self::totals(array_values($items->all()), $order->mobilization_fee, $order->minimum_charge);

        $order->update(['subtotal' => $subtotal->amount, 'total' => $total->amount]);

        return $order;
    }

    /**
     * @param  list<InstallationOrderItem>  $items
     * @return array{0: Money, 1: Money}
     */
    public static function totals(array $items, string|Money $mobilization, string|Money $minimum): array
    {
        $subtotal = Money::zero();
        foreach ($items as $item) {
            if ($item->countsTowardsTotal()) {
                $subtotal = $subtotal->plus($item->line_total);
            }
        }

        $total = $subtotal->plus(Money::of($mobilization))->max(Money::of($minimum));

        return [$subtotal, $total];
    }

    // ------------------------------------------------------------------ Programación

    public function schedule(InstallationOrder $order, Carbon $start, Carbon $end, User $actor): InstallationOrder
    {
        $order = DB::transaction(function () use ($order, $start, $end, $actor) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [InstallationStatus::Draft, InstallationStatus::Scheduled], true)) {
                throw ValidationException::withMessages(['status' => 'La instalación ya inició; no se puede reprogramar.']);
            }

            if ($locked->lead_installer_id === null) {
                throw ValidationException::withMessages(['lead_installer_id' => 'Asigna un instalador líder antes de programar.']);
            }

            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos una partida antes de programar.']);
            }

            $project = $locked->project;
            if (! in_array($project->status, self::SCHEDULABLE_PROJECT_STATUSES, true)) {
                throw ValidationException::withMessages(['scheduled_start' => 'El mueble aún no está listo para instalar (fabricación sin terminar).']);
            }

            $before = $locked->only(['status', 'scheduled_start', 'scheduled_end']);
            $locked->update(['status' => InstallationStatus::Scheduled, 'scheduled_start' => $start, 'scheduled_end' => $end]);

            $this->logger->log('instalacion.programada', $locked, ['before' => $before, 'after' => $locked->only(['status', 'scheduled_start', 'scheduled_end'])]);
            $this->progress->advance($project, ProjectStatus::InstallationScheduled, $actor, "Instalación {$locked->folio} programada.");

            return $locked;
        });

        $this->notifier->client($order->project, new InstallationScheduled($order));

        return $order;
    }

    public function cancel(InstallationOrder $order, User $actor, string $reason): InstallationOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            if (in_array($order->status, [InstallationStatus::Approved, InstallationStatus::PaymentAuthorized, InstallationStatus::Paid, InstallationStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'Esta orden ya no se puede cancelar.']);
            }

            $before = $order->status;
            $order->update(['status' => InstallationStatus::Cancelled, 'notes' => trim(($order->notes ? $order->notes."\n" : '').'Cancelada: '.$reason)]);
            $this->logger->log('instalacion.cancelada', $order, ['before' => ['status' => $before->value], 'reason' => $reason]);
            $this->progress->workflow()->record($order->project, 'installation_cancelled', "Instalación {$order->folio} cancelada", $reason, $actor);

            return $order;
        });
    }

    // ------------------------------------------------------------------ Ejecución (técnico)

    public function start(InstallationOrder $order, User $installer): InstallationOrder
    {
        return DB::transaction(function () use ($order, $installer) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InstallationStatus::InProgress) {
                return $locked;
            }

            if ($locked->status !== InstallationStatus::Scheduled) {
                throw ValidationException::withMessages(['status' => 'Solo se inicia una instalación programada.']);
            }

            $locked->update(['status' => InstallationStatus::InProgress, 'started_at' => now()]);
            $this->logger->log('instalacion.iniciada', $locked, ['by' => $installer->id]);
            $this->progress->advance($locked->project, ProjectStatus::Installing, $installer, "Inició la instalación {$locked->folio}.");

            return $locked;
        });
    }

    /**
     * Marca una partida como terminada con evidencia y autoevaluación.
     *
     * @param  list<int>  $mediaIds
     * @param  list<array{criterion: string, result: string, notes?: string|null}>  $checklist
     */
    public function completeItem(InstallationOrderItem $item, User $installer, array $mediaIds, array $checklist, ?string $notes): InstallationOrderItem
    {
        return DB::transaction(function () use ($item, $installer, $mediaIds, $checklist, $notes) {
            $order = $item->order;

            if ($order->status !== InstallationStatus::InProgress) {
                throw ValidationException::withMessages(['status' => 'Inicia la instalación antes de registrar avances.']);
            }

            if ($item->status === InstallationItemStatus::Rejected) {
                throw ValidationException::withMessages(['item' => 'Este extra fue rechazado; no requiere evidencia.']);
            }

            $this->attachments->attach($item, $mediaIds, 'evidence', $installer, evidenceOnly: true);

            if (! $item->attachments()->where('role', 'evidence')->exists()) {
                throw ValidationException::withMessages(['media_ids' => 'Sube al menos una foto o video de la partida terminada.']);
            }

            $this->saveChecklist($order, $item, QualityStage::Installer, $installer, $checklist, $notes);

            $item->update(['status' => InstallationItemStatus::Done]);
            $this->logger->log('instalacion.partida_terminada', $order, ['item' => $item->id, 'by' => $installer->id]);

            return $item;
        });
    }

    /** Envía la evidencia: pasa a revisión del cliente y se le avisa. */
    public function submitEvidence(InstallationOrder $order, User $installer): InstallationOrder
    {
        $order = DB::transaction(function () use ($order, $installer) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [InstallationStatus::EvidenceSubmitted, InstallationStatus::ClientReview], true)) {
                return $locked;
            }

            if ($locked->status !== InstallationStatus::InProgress) {
                throw ValidationException::withMessages(['status' => 'La instalación no está en curso.']);
            }

            $pending = $locked->items()
                ->where('status', InstallationItemStatus::Pending->value)
                ->get()
                ->filter(fn (InstallationOrderItem $i) => $i->countsTowardsTotal() || $i->isPendingExtra());

            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Faltan partidas por terminar: '.$pending->pluck('code')->implode(', ').'.',
                ]);
            }

            // Tras correcciones de calidad, el cliente vuelve a revisar: la respuesta
            // anterior queda en la bitácora y se limpia para pedir una nueva.
            $previousAnswer = $locked->only(['client_conformity', 'client_comment', 'client_signed_at']);

            $locked->update([
                'status' => InstallationStatus::EvidenceSubmitted,
                'evidence_submitted_at' => now(),
                'client_conformity' => null,
                'client_comment' => null,
                'client_signed_at' => null,
                'client_signed_by' => null,
            ]);
            $this->logger->log('instalacion.evidencia_enviada', $locked, ['by' => $installer->id, 'respuesta_anterior' => $previousAnswer]);

            $locked->update(['status' => InstallationStatus::ClientReview]);
            $this->progress->workflow()->record(
                $locked->project, 'installation_evidence', 'Instalación lista para tu revisión',
                'Revisa las fotos de cada módulo y confírmanos si todo quedó bien.', $installer, 'client',
                metadata: ['installation_order_id' => $locked->id],
            );

            return $locked;
        });

        $this->notifier->client($order->project, new InstallationEvidenceReady($order));

        return $order;
    }

    // ------------------------------------------------------------------ Conformidad del cliente

    /**
     * El cliente revisa la evidencia. "Hay pendientes" crea incidencias de
     * origen cliente. Su respuesta NO es renuncia a garantías ni derechos.
     *
     * @param  list<int>  $pendingItemIds
     * @param  list<int>  $mediaIds
     */
    public function clientConformity(InstallationOrder $order, User $client, ClientConformity $conformity, ?string $comment, array $pendingItemIds, array $mediaIds): InstallationOrder
    {
        return DB::transaction(function () use ($order, $client, $conformity, $comment, $pendingItemIds, $mediaIds) {
            /** @var InstallationOrder $locked */
            $locked = InstallationOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== InstallationStatus::ClientReview) {
                throw ValidationException::withMessages(['conformity' => 'Esta instalación ya no espera tu revisión.']);
            }

            if ($conformity === ClientConformity::Pending && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Cuéntanos qué está pendiente.']);
            }

            $this->attachments->attach($locked, $mediaIds, 'client_photo', $client, evidenceOnly: true);

            $locked->update([
                'client_conformity' => $conformity,
                'client_comment' => $comment,
                'client_signed_at' => now(),
                'client_signed_by' => $client->id,
                'status' => InstallationStatus::QualityReview,
            ]);

            if ($conformity === ClientConformity::Pending) {
                $items = $locked->items()->whereIn('id', $pendingItemIds)->get();
                $targets = $items->isEmpty() ? [null] : $items->all();

                foreach ($targets as $target) {
                    $incident = $this->incidents->create([
                        'project_id' => $locked->project_id,
                        'installation_order_id' => $locked->id,
                        'category' => IncidentCategory::InstallationError->value,
                        'severity' => IncidentSeverity::Medium->value,
                        'title' => $target ? "Pendiente en {$target->code}: {$target->description}" : 'Pendiente reportado por el cliente',
                        'description' => (string) $comment,
                        'visible_to_client' => true,
                        'responsible_party' => null,
                    ], $client, IncidentOrigin::Client);

                    if ($mediaIds !== []) {
                        foreach ($mediaIds as $mediaId) {
                            $incident->attachMedia($mediaId, 'evidence');
                        }
                    }
                }
            }

            $this->logger->log('instalacion.conformidad_cliente', $locked, [
                'conformity' => $conformity->value,
                'pending_items' => $pendingItemIds,
            ]);
            $this->progress->workflow()->record(
                $locked->project, 'client_conformity',
                $conformity === ClientConformity::Conforming ? 'Confirmaste que la instalación quedó conforme' : 'Reportaste pendientes de la instalación',
                $comment, $client, 'client',
            );
            $this->progress->advance($locked->project, ProjectStatus::QualityReview, $client, 'Instalación en revisión de calidad.');

            return $locked;
        });
    }

    /** Pasa a calidad sin respuesta del cliente (con motivo registrado). */
    public function skipClientReview(InstallationOrder $order, User $actor, string $reason): InstallationOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            if ($order->status !== InstallationStatus::ClientReview) {
                throw ValidationException::withMessages(['status' => 'La orden no está en revisión del cliente.']);
            }

            $order->update(['status' => InstallationStatus::QualityReview]);
            $this->logger->log('instalacion.calidad_sin_cliente', $order, ['reason' => $reason]);
            $this->progress->workflow()->record($order->project, 'client_review_skipped', 'Revisión de calidad sin respuesta del cliente', $reason, $actor);
            $this->progress->advance($order->project, ProjectStatus::QualityReview, $actor);

            return $order;
        });
    }

    // ------------------------------------------------------------------ Apoyo

    /**
     * Guarda (o reemplaza) la inspección de una etapa para una partida con
     * los 11 criterios del checklist.
     *
     * @param  list<array{criterion: string, result: string, notes?: string|null}>  $checklist
     */
    public function saveChecklist(InstallationOrder $order, ?InstallationOrderItem $item, QualityStage $stage, User $inspector, array $checklist, ?string $observations, QualityResult $result = QualityResult::Approved, bool $clientContacted = false): QualityInspection
    {
        $byCriterion = collect($checklist)->keyBy('criterion');
        $missing = collect(QualityCriterion::cases())->reject(fn (QualityCriterion $c) => $byCriterion->has($c->value));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => 'Completa el checklist: falta '.$missing->map(fn (QualityCriterion $c) => $c->label())->implode(', ').'.',
            ]);
        }

        if ($stage === QualityStage::Installer && $byCriterion->contains(fn ($c) => $c['result'] === CheckResult::Fail->value && trim((string) ($c['notes'] ?? '')) === '')) {
            throw ValidationException::withMessages(['checklist' => 'Explica en una nota cada criterio que no cumple.']);
        }

        $inspection = QualityInspection::query()->updateOrCreate(
            ['installation_order_id' => $order->id, 'installation_order_item_id' => $item?->id, 'stage' => $stage->value],
            [
                'result' => $result,
                'observations' => $observations,
                'client_contacted' => $clientContacted,
                'inspector_id' => $inspector->id,
                'inspected_at' => now(),
            ],
        );

        $inspection->checks()->delete();
        foreach (QualityCriterion::cases() as $criterion) {
            $row = $byCriterion->get($criterion->value);
            $inspection->checks()->create([
                'criterion' => $criterion,
                'result' => CheckResult::from($row['result']),
                'notes' => $row['notes'] ?? null,
            ]);
        }

        return $inspection;
    }

    /**
     * @param  list<array{rate_card_item_id: int, quantity: string}>  $items
     */
    private function addRateCardItems(InstallationOrder $order, RateCard $card, array $items): void
    {
        $catalog = $card->items()->get()->keyBy('id');
        $sort = (int) $order->items()->max('sort');

        foreach ($items as $row) {
            /** @var RateCardItem|null $rateItem */
            $rateItem = $catalog->get((int) $row['rate_card_item_id']);
            if ($rateItem === null) {
                throw ValidationException::withMessages(['items' => 'Un concepto no pertenece al tarifario elegido.']);
            }

            $order->items()->create([
                'rate_card_item_id' => $rateItem->id,
                'code' => $rateItem->code,
                'description' => $rateItem->description,
                'unit' => $rateItem->unit,
                'quantity' => $row['quantity'],
                'unit_price' => $rateItem->unit_price,
                'line_total' => Money::of($rateItem->unit_price)->times($row['quantity'])->amount,
                'is_extra' => false,
                'status' => InstallationItemStatus::Pending,
                'sort' => ++$sort,
            ]);
        }
    }

    private function installer(int $userId): User
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('role', UserRole::Technician->value)
            ->where('is_active', true)
            ->whereHas('technicianProfile', fn ($q) => $q->where('does_installations', true)->where('status', TechnicianStatus::Active->value))
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages(['lead_installer_id' => 'Elige un técnico activo que haga instalaciones.']);
        }

        return $user;
    }

    private function ensureEditable(InstallationOrder $order): void
    {
        if (! $order->status->isEditable()) {
            throw ValidationException::withMessages(['items' => 'Las partidas ya no se pueden modificar; registra un extra si hace falta.']);
        }
    }

    private function ensureNotClosed(InstallationOrder $order): void
    {
        if (in_array($order->status, [InstallationStatus::Cancelled, InstallationStatus::Paid], true)) {
            throw ValidationException::withMessages(['status' => 'La orden está cerrada.']);
        }
    }
}
