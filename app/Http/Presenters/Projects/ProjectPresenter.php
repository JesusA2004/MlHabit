<?php

namespace App\Http\Presenters\Projects;

use App\Enums\ProjectStatus;
use App\Models\Address;
use App\Models\Contract;
use App\Models\DesignVersion;
use App\Models\Measurement;
use App\Models\MeasurementItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Arrays explícitos para props de Inertia (nunca modelos completos).
 * Los archivos privados se enlazan SIEMPRE por /archivos/{uuid}
 * (archivos.show), que autoriza contra la Policy de la entidad dueña.
 */
final class ProjectPresenter
{
    /**
     * Enum (de cualquier módulo) → {value, label, tone}.
     *
     * @return array{value: string, label: string, tone: string}|null
     */
    public static function enum(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! $value instanceof BackedEnum) {
            return ['value' => (string) $value, 'label' => (string) $value, 'tone' => 'neutral'];
        }

        return [
            'value' => (string) $value->value,
            'label' => method_exists($value, 'label') ? (string) $value->label() : (string) $value->value,
            'tone' => method_exists($value, 'tone') ? (string) $value->tone() : 'neutral',
        ];
    }

    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? Carbon::instance($value)->toIso8601String() : (string) $value;
    }

    public static function day(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }

    /** @return array{id: int, name: string}|null */
    public static function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    /** @return array<string, mixed> */
    public static function status(ProjectStatus $status): array
    {
        return [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
            'client_description' => $status->clientDescription(),
            'journey_step' => $status->journeyStep(),
        ];
    }

    /**
     * Pasos del recorrido del cliente (ProjectStatus::journeyStep 1..7).
     *
     * @return list<array{key: string, title: string, description: string}>
     */
    public static function journey(): array
    {
        return [
            ['key' => '1', 'title' => 'Medición', 'description' => 'Tomamos medidas exactas de tu espacio.'],
            ['key' => '2', 'title' => 'Diseño y cotización', 'description' => 'Diseñamos tu mueble y te cotizamos.'],
            ['key' => '3', 'title' => 'Contrato y anticipo', 'description' => 'Formalizamos y apartamos fabricación.'],
            ['key' => '4', 'title' => 'Fabricación', 'description' => 'Fabricamos tu mueble a la medida.'],
            ['key' => '5', 'title' => 'Instalación', 'description' => 'Instalamos en tu espacio.'],
            ['key' => '6', 'title' => 'Revisión de calidad', 'description' => 'Revisamos cada detalle.'],
            ['key' => '7', 'title' => 'Entrega', 'description' => 'Tu proyecto queda listo.'],
        ];
    }

    /** @return array<string, mixed> */
    public static function summary(Project $project): array
    {
        return [
            'uuid' => $project->uuid,
            'folio' => $project->folio,
            'name' => $project->name,
            'status' => self::status($project->status),
            'customer' => $project->relationLoaded('customer') && $project->customer
                ? ['uuid' => $project->customer->uuid, 'name' => $project->customer->full_name]
                : null,
            'category' => $project->relationLoaded('category') && $project->category ? $project->category->getAttribute('name') : null,
            'advisor' => $project->relationLoaded('advisor') ? self::person($project->advisor) : null,
            'designer' => $project->relationLoaded('designer') ? self::person($project->designer) : null,
            'target_install_date' => self::day($project->target_install_date),
            'created_at' => self::date($project->created_at),
            'updated_at' => self::date($project->updated_at),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function address(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'id' => $address->id,
            'label' => $address->label,
            'one_line' => $address->one_line,
            'references' => $address->references,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address->one_line),
        ];
    }

    /** @return array<string, mixed> */
    public static function event(ProjectEvent $event, bool $forClient = false): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'title' => $event->title,
            'body' => $event->body,
            'visibility' => $event->visibility,
            'to_status' => $event->to_status,
            'actor' => $forClient ? null : ($event->relationLoaded('user') ? $event->user?->name : null),
            'occurred_at' => self::date($event->occurred_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function file(MediaAttachment $attachment): array
    {
        /** @var Media|null $media */
        $media = $attachment->relationLoaded('media') ? $attachment->media : $attachment->media()->first();

        return [
            'id' => $attachment->id,
            'role' => $attachment->role,
            'caption' => $attachment->caption,
            'uuid' => $media?->uuid,
            'name' => $media ? ($media->title ?: $media->original_filename) : 'Archivo no disponible',
            'mime' => $media?->mime_type,
            'size' => $media?->size,
            'is_image' => $media !== null && str_starts_with((string) $media->mime_type, 'image/'),
            'url' => $media ? '/archivos/'.$media->uuid : null,
            'created_at' => self::date($attachment->created_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function measurement(Measurement $measurement): array
    {
        $attachments = $measurement->relationLoaded('attachments') ? $measurement->attachments : $measurement->attachments()->with('media')->get();

        return [
            'id' => $measurement->id,
            'version' => $measurement->version,
            'status' => $measurement->status->toArray(),
            'space_type' => $measurement->space_type,
            'access_notes' => $measurement->access_notes,
            'existing_installations' => $measurement->existing_installations,
            'general_notes' => $measurement->general_notes,
            'technician' => $measurement->relationLoaded('technician') ? self::person($measurement->technician) : null,
            'recorded_at' => self::date($measurement->recorded_at),
            'submitted_at' => self::date($measurement->submitted_at),
            'supersedes_id' => $measurement->supersedes_id,
            'items' => $measurement->items->map(fn (MeasurementItem $item) => [
                'id' => $item->id,
                'area' => $item->area,
                'kind' => $item->kind->value,
                'kind_label' => $item->kind->label(),
                'label' => $item->label,
                'value_mm' => $item->value_mm,
                'notes' => $item->notes,
            ])->values()->all(),
            'photos' => $attachments->where('role', 'photo')->map(fn ($a) => self::file($a))->values()->all(),
            'sketches' => $attachments->where('role', 'sketch')->map(fn ($a) => self::file($a))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function design(DesignVersion $design, bool $forClient = false): array
    {
        $files = $design->relationLoaded('attachments') ? $design->attachments : $design->attachments()->with('media')->get();

        return [
            'id' => $design->id,
            'number' => $design->number,
            'title' => $design->title,
            'notes' => $design->notes,
            'status' => [
                ...$design->status->toArray(),
                'client_label' => $design->status->clientLabel(),
            ],
            'measurement_id' => $forClient ? null : $design->measurement_id,
            'sent_at' => self::date($design->sent_at),
            'client_approved_layout' => $design->client_approved_layout,
            'client_approved_finishes' => $design->client_approved_finishes,
            'client_decided_at' => self::date($design->client_decided_at),
            'client_comment' => $design->client_comment,
            'technical_approved_at' => $forClient ? null : self::date($design->technical_approved_at),
            'technical_approved_by' => $forClient || ! $design->relationLoaded('technicalApprover') ? null : $design->technicalApprover?->name,
            'technical_notes' => $forClient ? null : $design->technical_notes,
            'created_by' => $forClient || ! $design->relationLoaded('creator') ? null : $design->creator?->name,
            'created_at' => self::date($design->created_at),
            'files' => $files->whereIn('role', ['render', 'file'])->map(fn ($a) => self::file($a))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function quoteSummary(Quote $quote): array
    {
        return [
            'uuid' => $quote->uuid,
            'folio' => $quote->folio,
            'version' => $quote->version,
            'title' => $quote->title,
            'status' => [...$quote->status->toArray(), 'client_label' => $quote->status->clientLabel()],
            'total' => $quote->total,
            'currency' => $quote->currency,
            'valid_until' => self::day($quote->valid_until),
            'sent_at' => self::date($quote->sent_at),
            'accepted_at' => self::date($quote->accepted_at),
            'rejected_at' => self::date($quote->rejected_at),
            'rejection_reason' => $quote->rejection_reason,
            'created_at' => self::date($quote->created_at),
            'design_number' => $quote->relationLoaded('designVersion') ? $quote->designVersion?->number : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function quote(Quote $quote, bool $forClient = false): array
    {
        return [
            ...self::quoteSummary($quote),
            'design_version_id' => $quote->design_version_id,
            'subtotal' => $quote->subtotal,
            'discount_total' => $quote->discount_total,
            'tax_rate' => $quote->tax_rate,
            'tax_percent' => is_numeric($quote->tax_rate) ? rtrim(rtrim(bcmul($quote->tax_rate, '100', 2), '0'), '.') : '0',
            'tax_total' => $quote->tax_total,
            'prices_include_tax' => $quote->prices_include_tax,
            'inclusions' => $quote->inclusions,
            'exclusions' => $quote->exclusions,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'discount_authorized_by' => $forClient || ! $quote->relationLoaded('discountAuthorizer') ? null : $quote->discountAuthorizer?->name,
            'accepted_by' => $forClient || ! $quote->relationLoaded('acceptedBy') ? null : $quote->acceptedBy?->name,
            'items' => $quote->items->map(fn (QuoteItem $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
                'line_total' => $item->line_total,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function contract(Contract $contract, array $extra = []): array
    {
        return [
            'uuid' => $contract->uuid,
            'folio' => $contract->folio,
            'status' => $contract->status->toArray(),
            'contract_amount' => $contract->contract_amount,
            'deposit_percent' => $contract->deposit_percent,
            'deposit_amount' => $contract->deposit_amount,
            'visit_credit_amount' => $contract->visit_credit_amount,
            'deposit_due_amount' => $contract->deposit_due_amount,
            'currency' => $contract->currency,
            'signed_at' => self::date($contract->signed_at),
            'deposit_paid_at' => self::date($contract->deposit_paid_at),
            'quote_uuid' => $contract->relationLoaded('quote') ? $contract->quote?->uuid : null,
            'quote_folio' => $contract->relationLoaded('quote') ? $contract->quote?->folio : null,
            ...$extra,
        ];
    }

    /** @return array<string, mixed> */
    public static function payment(Model $payment, bool $forClient = false): array
    {
        $uuid = (string) $payment->getAttribute('uuid');

        return [
            'uuid' => $uuid,
            'folio' => $payment->getAttribute('folio'),
            'concept' => self::enum($payment->getAttribute('concept')),
            'method' => self::enum($payment->getAttribute('method')),
            'status' => self::enum($payment->getAttribute('status')),
            'description' => $payment->getAttribute('description'),
            'amount' => (string) $payment->getAttribute('amount'),
            'refunded_amount' => (string) ($payment->getAttribute('refunded_amount') ?? '0.00'),
            'card_brand' => $payment->getAttribute('card_brand'),
            'card_last4' => $payment->getAttribute('card_last4'),
            'reference' => $forClient ? null : $payment->getAttribute('reference'),
            'paid_at' => self::date($payment->getAttribute('paid_at')),
            'created_at' => self::date($payment->getAttribute('created_at')),
            'receipt_url' => $uuid !== '' ? '/pagos/'.$uuid.'/comprobante' : null,
        ];
    }
}
