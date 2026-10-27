<?php

namespace App\Http\Presenters\Operations;

use App\Enums\FabricationStatus;
use App\Enums\QualityStage;
use App\Models\FabricationOrder;
use App\Models\Incident;
use App\Models\InstallationOrder;
use App\Models\InstallationOrderItem;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Models\QualityInspection;
use App\Models\RateCard;
use App\Models\Supplier;
use App\Models\SupplierPayout;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\Operations\Attachments;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Arreglos explícitos para props de Inertia del módulo de operaciones.
 * Nunca se envían modelos completos: solo lo que la pantalla necesita.
 */
final class OpsPresenter
{
    /** @return array{id: int, name: string}|null */
    public static function user(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    /** @return array<string, mixed> */
    public static function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'folio' => $project->folio,
            'name' => $project->name,
            'status' => ['value' => $project->status->value, 'label' => $project->status->label(), 'tone' => $project->status->tone()],
            'customer' => $project->relationLoaded('customer') ? $project->customer?->full_name : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function supplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => $supplier->type->toArray(),
            'status' => $supplier->status->toArray(),
            'rating' => $supplier->rating,
            'leadTimeDays' => $supplier->lead_time_days,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
        ];
    }

    /** @return array<string, mixed> */
    public static function rateCard(RateCard $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'version' => $card->version,
            'status' => $card->status->toArray(),
            'supplier' => $card->relationLoaded('supplier') && $card->supplier ? self::supplier($card->supplier) : null,
            'mobilizationFee' => $card->mobilization_fee,
            'minimumCharge' => $card->minimum_charge,
            'effectiveFrom' => $card->effective_from?->toDateString(),
            'activatedAt' => $card->activated_at?->toIso8601String(),
            'notes' => $card->notes,
            'itemsCount' => $card->items_count ?? ($card->relationLoaded('items') ? $card->items->count() : null),
            'items' => $card->relationLoaded('items') ? $card->items->map(fn ($i) => [
                'id' => $i->id,
                'code' => $i->code,
                'description' => $i->description,
                'unit' => $i->unit,
                'unitPrice' => $i->unit_price,
            ])->values()->all() : null,
            'updatedAt' => $card->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function fabrication(FabricationOrder $order): array
    {
        return [
            'id' => $order->id,
            'folio' => $order->folio,
            'status' => $order->status->toArray(),
            'project' => self::project($order->project),
            'supplier' => self::supplier($order->supplier),
            'designVersionId' => $order->design_version_id,
            'cost' => $order->cost,
            'dueOn' => $order->due_on?->toDateString(),
            'overdue' => $order->due_on !== null && $order->due_on->endOfDay()->isPast() && ! $order->status->isFinished() && $order->status !== FabricationStatus::Cancelled,
            'issuedAt' => $order->issued_at?->toIso8601String(),
            'readyAt' => $order->ready_at?->toIso8601String(),
            'deliveredAt' => $order->delivered_at?->toIso8601String(),
            'notes' => $order->notes,
            'createdAt' => $order->created_at?->toIso8601String(),
            'nextStatuses' => array_map(fn ($s) => $s->toArray(), $order->status->allowedTransitions()),
        ];
    }

    /** @return array<string, mixed> */
    public static function installationRow(InstallationOrder $order): array
    {
        return [
            'id' => $order->id,
            'folio' => $order->folio,
            'status' => $order->status->toArray(),
            'project' => self::project($order->project),
            'supplier' => $order->relationLoaded('supplier') ? self::supplier($order->supplier) : null,
            'leadInstaller' => $order->relationLoaded('leadInstaller') ? self::user($order->leadInstaller) : null,
            'scheduledStart' => $order->scheduled_start?->toIso8601String(),
            'scheduledEnd' => $order->scheduled_end?->toIso8601String(),
            'total' => $order->total,
            'itemsCount' => $order->items_count ?? null,
            'doneCount' => $order->done_count ?? null,
            'pendingExtras' => $order->pending_extras_count ?? null,
            'clientConformity' => $order->client_conformity?->toArray(),
            'evidenceSubmittedAt' => $order->evidence_submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Detalle de la orden. `$audience` controla qué se expone:
     * staff (todo), installer (sin datos de cobro al cliente), client
     * (sin precios ni notas internas).
     *
     * @param  'staff'|'installer'|'client'  $audience
     * @return array<string, mixed>
     */
    public static function installation(InstallationOrder $order, string $audience = 'staff'): array
    {
        $order->loadMissing([
            'project.customer', 'project.address', 'supplier', 'leadInstaller', 'rateCard',
            'items.attachments.media', 'items.inspections.checks', 'items.inspections.inspector', 'inspections.inspector', 'payout',
        ]);

        $showMoney = $audience !== 'client';

        $items = $order->items->map(function (InstallationOrderItem $item) use ($showMoney, $audience) {
            $installer = $item->inspections->firstWhere('stage', QualityStage::Installer);
            $quality = $item->inspections->firstWhere('stage', QualityStage::Quality);

            return [
                'id' => $item->id,
                'code' => $item->code,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unitPrice' => $showMoney ? $item->unit_price : null,
                'lineTotal' => $showMoney ? $item->line_total : null,
                'isExtra' => $item->is_extra,
                'extraApproved' => $item->extra_approved_at !== null,
                'pendingExtra' => $item->isPendingExtra(),
                'countsTowardsTotal' => $item->countsTowardsTotal(),
                'status' => $item->status->toArray(),
                'evidence' => self::attached($item->attachments, 'evidence'),
                'installerCheck' => $installer ? self::inspection($installer) : null,
                'qualityCheck' => $quality && $audience === 'staff' ? self::inspection($quality) : null,
            ];
        })->values()->all();

        $orderReview = $order->inspections->first(fn (QualityInspection $i) => $i->installation_order_item_id === null && $i->stage === QualityStage::Quality);
        $address = $order->project->address;

        return [
            'id' => $order->id,
            'folio' => $order->folio,
            'status' => $order->status->toArray(),
            'step' => $order->status->step(),
            'project' => self::project($order->project),
            'address' => $address && $audience !== 'client' ? trim("{$address->street} {$address->exterior_number}".($address->interior_number ? " int. {$address->interior_number}" : '').", {$address->neighborhood}, {$address->municipality}") : null,
            'addressReferences' => $address && $audience !== 'client' ? $address->references : null,
            'supplier' => $audience === 'staff' ? self::supplier($order->supplier) : ['id' => $order->supplier->id, 'name' => $order->supplier->name],
            'leadInstaller' => self::user($order->leadInstaller),
            'rateCard' => $audience === 'staff' && $order->rateCard ? ['id' => $order->rateCard->id, 'name' => $order->rateCard->name, 'version' => $order->rateCard->version, 'status' => $order->rateCard->status->toArray()] : null,
            'scheduledStart' => $order->scheduled_start?->toIso8601String(),
            'scheduledEnd' => $order->scheduled_end?->toIso8601String(),
            'startedAt' => $order->started_at?->toIso8601String(),
            'evidenceSubmittedAt' => $order->evidence_submitted_at?->toIso8601String(),
            'completedAt' => $order->completed_at?->toIso8601String(),
            'mobilizationFee' => $showMoney ? $order->mobilization_fee : null,
            'minimumCharge' => $showMoney ? $order->minimum_charge : null,
            'subtotal' => $showMoney ? $order->subtotal : null,
            'total' => $showMoney ? $order->total : null,
            'minimumApplied' => $showMoney && Money::of($order->total)->compare(Money::of($order->subtotal)->plus($order->mobilization_fee)) > 0,
            'clientConformity' => $order->client_conformity?->toArray(),
            'clientComment' => $order->client_comment,
            'clientSignedAt' => $order->client_signed_at?->toIso8601String(),
            'clientPhotos' => Attachments::presentMany($order->mediaWithRole('client_photo')->get()),
            'notes' => $audience === 'client' ? null : $order->notes,
            'items' => $items,
            'qualityReview' => $orderReview && $audience === 'staff' ? self::inspection($orderReview) : null,
            'payout' => $audience === 'staff' && $order->payout ? self::payoutRow($order->payout) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function inspection(QualityInspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'stage' => $inspection->stage->toArray(),
            'result' => $inspection->result->toArray(),
            'observations' => $inspection->observations,
            'clientContacted' => $inspection->client_contacted,
            'inspector' => self::user($inspection->inspector),
            'inspectedAt' => $inspection->inspected_at?->toIso8601String(),
            'checks' => $inspection->relationLoaded('checks') ? $inspection->checks->map(fn ($c) => [
                'criterion' => $c->criterion->value,
                'label' => $c->criterion->label(),
                'result' => $c->result->toArray(),
                'notes' => $c->notes,
            ])->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    public static function payoutRow(SupplierPayout $payout): array
    {
        return [
            'id' => $payout->id,
            'folio' => $payout->folio,
            'status' => $payout->status->toArray(),
            'amount' => $payout->amount,
            'supplier' => $payout->relationLoaded('supplier') ? ['id' => $payout->supplier->id, 'name' => $payout->supplier->name] : null,
            'source' => $payout->installation_order_id
                ? ['kind' => 'installation', 'id' => $payout->installation_order_id, 'folio' => $payout->relationLoaded('installationOrder') ? $payout->installationOrder?->folio : null]
                : ['kind' => 'fabrication', 'id' => $payout->fabrication_order_id, 'folio' => $payout->relationLoaded('fabricationOrder') ? $payout->fabricationOrder?->folio : null],
            'requestedBy' => $payout->relationLoaded('requester') ? self::user($payout->requester) : null,
            'authorizedBy' => $payout->relationLoaded('authorizer') ? self::user($payout->authorizer) : null,
            'authorizedAt' => $payout->authorized_at?->toIso8601String(),
            'paidBy' => $payout->relationLoaded('payer') ? self::user($payout->payer) : null,
            'paidAt' => $payout->paid_at?->toIso8601String(),
            'reference' => $payout->payment_reference,
            'rejectionReason' => $payout->rejection_reason,
            'createdAt' => $payout->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function incident(Incident $incident, bool $detail = false): array
    {
        $row = [
            'id' => $incident->id,
            'folio' => $incident->folio,
            'title' => $incident->title,
            'status' => $incident->status->toArray(),
            'category' => $incident->category->toArray(),
            'severity' => $incident->severity->toArray(),
            'origin' => $incident->origin->toArray(),
            'project' => self::project($incident->project),
            'assignee' => $incident->relationLoaded('assignee') ? self::user($incident->assignee) : null,
            'dueOn' => $incident->due_on?->toDateString(),
            'overdue' => $incident->due_on !== null && $incident->status->isOpen() && $incident->due_on->endOfDay()->isPast(),
            'visibleToClient' => $incident->visible_to_client,
            'createdAt' => $incident->created_at?->toIso8601String(),
        ];

        if (! $detail) {
            return $row;
        }

        $incident->loadMissing(['installationOrder', 'fabricationOrder', 'responsibleSupplier', 'reporter', 'attachments.media']);

        return [
            ...$row,
            'description' => $incident->description,
            'responsibleParty' => $incident->responsible_party?->toArray(),
            'responsibleSupplier' => $incident->responsibleSupplier ? ['id' => $incident->responsibleSupplier->id, 'name' => $incident->responsibleSupplier->name] : null,
            'actionPlan' => $incident->action_plan,
            'cost' => $incident->cost,
            'resolution' => $incident->resolution,
            'resolvedAt' => $incident->resolved_at?->toIso8601String(),
            'reporter' => self::user($incident->reporter),
            'installationOrder' => $incident->installationOrder ? ['id' => $incident->installationOrder->id, 'folio' => $incident->installationOrder->folio] : null,
            'fabricationOrder' => $incident->fabricationOrder ? ['id' => $incident->fabricationOrder->id, 'folio' => $incident->fabricationOrder->folio] : null,
            'evidence' => self::attached($incident->attachments, 'evidence'),
            'nextStatuses' => array_map(fn ($s) => $s->toArray(), $incident->status->allowedTransitions()),
        ];
    }

    /** @return array<string, mixed> */
    public static function warranty(Warranty $warranty, bool $detail = false): array
    {
        $row = [
            'id' => $warranty->id,
            'folio' => $warranty->folio,
            'type' => $warranty->type->toArray(),
            'status' => $warranty->status->toArray(),
            'project' => self::project($warranty->project),
            'supplier' => $warranty->supplier ? ['id' => $warranty->supplier->id, 'name' => $warranty->supplier->name] : null,
            'startsOn' => $warranty->starts_on->toDateString(),
            'endsOn' => $warranty->ends_on->toDateString(),
            'inForce' => $warranty->isInForce(),
            'openClaims' => $warranty->open_claims_count ?? null,
        ];

        if (! $detail) {
            return $row;
        }

        return [
            ...$row,
            'scope' => $warranty->scope,
            'exclusions' => $warranty->exclusions,
            'claims' => $warranty->claims->map(fn (WarrantyClaim $c) => self::claim($c))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function claim(WarrantyClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'folio' => $claim->folio,
            'status' => $claim->status->toArray(),
            'description' => $claim->description,
            'resolution' => $claim->resolution,
            'resolvedAt' => $claim->resolved_at?->toIso8601String(),
            'createdAt' => $claim->created_at?->toIso8601String(),
            'reporter' => self::user($claim->reporter),
            'incident' => $claim->incident ? ['id' => $claim->incident->id, 'folio' => $claim->incident->folio] : null,
            'evidence' => Attachments::presentMany($claim->mediaWithRole('evidence')->get()),
        ];
    }

    /**
     * Archivos de un rol a partir de los vínculos ya cargados (sin consultas extra).
     *
     * @param  Collection<int, MediaAttachment>  $attachments
     * @return list<array<string, mixed>>
     */
    private static function attached(Collection $attachments, string $role): array
    {
        return Attachments::presentMany(
            $attachments->where('role', $role)->sortBy('sort')->map(fn (MediaAttachment $a) => $a->media)->filter()->values(),
        );
    }
}
