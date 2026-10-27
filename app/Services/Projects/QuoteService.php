<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Enums\QuoteStatus;
use App\Models\DesignVersion;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\Projects\QuoteAvailable;
use App\Services\ActivityLogger;
use App\Support\Folio;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cotizaciones versionadas.
 *  - Borrador: se edita en sitio (los totales se recalculan siempre).
 *  - Enviada/rechazada/vencida: editar crea la siguiente versión; la
 *    enviada queda `superseded`.
 *  - Aceptada o reemplazada: inmutable.
 *  - IVA y "precios con IVA" se congelan desde Settings al crear cada
 *    versión; la vigencia sale de commercial.quote_validity_days.
 *  - Cualquier descuento exige quotes.authorize_discount y queda
 *    registrado en discount_authorized_by.
 */
class QuoteService
{
    public function __construct(
        private readonly QuoteCalculator $calculator,
        private readonly ProjectWorkflow $workflow,
        private readonly ActivityLogger $logger,
        private readonly CustomerNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  title, design_version_id, inclusions, exclusions, notes, terms, items[]
     */
    public function create(Project $project, array $data, User $actor, ?Quote $from = null): Quote
    {
        $this->assertProjectOpen($project);
        $this->assertDesignBelongs($project, $data['design_version_id'] ?? null);

        return DB::transaction(function () use ($project, $data, $actor, $from) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $version = (int) Quote::query()->where('project_id', $project->id)->max('version') + 1;
            $includeTax = (bool) Settings::get('commercial.prices_include_tax', true);
            $taxRate = (string) Settings::get('commercial.tax_rate', '0.1600');

            /** @var Quote $quote */
            $quote = Quote::query()->create([
                'folio' => Folio::next('C'),
                'project_id' => $project->id,
                'design_version_id' => $data['design_version_id'] ?? null,
                'version' => $version,
                'status' => QuoteStatus::Draft,
                'title' => $data['title'],
                'valid_until' => $this->validUntil(),
                'currency' => (string) Settings::get('commercial.currency', 'MXN'),
                'tax_rate' => $taxRate,
                'prices_include_tax' => $includeTax,
                'inclusions' => $data['inclusions'] ?? null,
                'exclusions' => $data['exclusions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->writeItemsAndTotals($quote, $data['items'] ?? [], $actor);

            if ($from && $from->status === QuoteStatus::Sent) {
                $from->update(['status' => QuoteStatus::Superseded]);
            }

            $title = $from ? "Cotización v{$version} creada (corrige la v{$from->version})" : "Cotización v{$version} creada";
            $this->workflow->record($project, 'quote_created', $title, null, $actor, metadata: ['quote_id' => $quote->id]);
            $this->logger->log('cotizacion.creada', $quote, ['folio' => $quote->folio, 'from' => $from?->folio, 'total' => $quote->total]);

            return $quote->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Quote $quote, array $data, User $actor): Quote
    {
        if (in_array($quote->status, [QuoteStatus::Accepted, QuoteStatus::Superseded], true)) {
            throw ValidationException::withMessages(['status' => 'Esta cotización ya no se puede modificar.']);
        }

        if ($quote->status !== QuoteStatus::Draft) {
            return $this->create($quote->project, $data, $actor, $quote);
        }

        $this->assertDesignBelongs($quote->project, $data['design_version_id'] ?? null);

        return DB::transaction(function () use ($quote, $data, $actor) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== QuoteStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'La cotización cambió mientras la editabas. Recarga la página.']);
            }

            $before = $locked->only(['title', 'subtotal', 'discount_total', 'total']);
            $locked->update([
                'title' => $data['title'],
                'design_version_id' => $data['design_version_id'] ?? null,
                'inclusions' => $data['inclusions'] ?? null,
                'exclusions' => $data['exclusions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);
            $this->writeItemsAndTotals($locked, $data['items'] ?? [], $actor);
            $locked->refresh();

            $this->logger->log('cotizacion.actualizada', $locked, [
                'before' => $before,
                'after' => $locked->only(['title', 'subtotal', 'discount_total', 'total']),
            ]);

            return $locked;
        });
    }

    public function send(Quote $quote, User $actor): Quote
    {
        $quote = DB::transaction(function () use ($quote, $actor) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === QuoteStatus::Sent) {
                return $locked;
            }
            if ($locked->status !== QuoteStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Solo se envía una cotización en borrador.']);
            }
            if (! $locked->items()->exists() || ! Money::of($locked->total)->isPositive()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos una partida con importe antes de enviar.']);
            }

            $locked->update([
                'status' => QuoteStatus::Sent,
                'sent_at' => now(),
                'valid_until' => $locked->isPastValidity() || $locked->valid_until === null ? $this->validUntil() : $locked->valid_until,
            ]);

            $project = $locked->project;
            $this->advanceToQuoting($project, $actor);
            $this->workflow->record($project, 'quote_sent', 'Tu cotización está lista', "Revisa la cotización {$locked->folio} en tu portal.", $actor, 'client', metadata: ['quote_id' => $locked->id]);
            $this->logger->log('cotizacion.enviada', $locked, ['folio' => $locked->folio, 'total' => $locked->total]);

            return $locked;
        });

        $this->notifier->notify($quote->project->customer, new QuoteAvailable($quote));

        return $quote;
    }

    public function reject(Quote $quote, User $actor, string $reason): Quote
    {
        return DB::transaction(function () use ($quote, $actor, $reason) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === QuoteStatus::Rejected) {
                return $locked;
            }
            if ($locked->status !== QuoteStatus::Sent) {
                throw ValidationException::withMessages(['status' => 'Esta cotización ya no espera respuesta.']);
            }

            $locked->update(['status' => QuoteStatus::Rejected, 'rejected_at' => now(), 'rejection_reason' => $reason]);

            $isClient = ! $actor->isStaff();
            $this->workflow->record(
                $locked->project,
                'quote_rejected',
                $isClient ? "No aceptaste la cotización {$locked->folio}" : "Cotización {$locked->folio} rechazada",
                $reason,
                $actor,
                $isClient ? 'client' : 'internal',
                metadata: ['quote_id' => $locked->id],
            );
            $this->logger->log('cotizacion.rechazada', $locked, ['reason' => $reason]);

            return $locked;
        });
    }

    /** Marca como vencidas las enviadas cuya vigencia ya pasó. Devuelve cuántas. */
    public function expireDue(): int
    {
        $count = 0;
        Quote::query()
            ->where('status', QuoteStatus::Sent)
            ->whereDate('valid_until', '<', now()->toDateString())
            ->orderBy('id')
            ->each(function (Quote $quote) use (&$count) {
                DB::transaction(function () use ($quote, &$count) {
                    /** @var Quote $locked */
                    $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== QuoteStatus::Sent || ! $locked->isPastValidity()) {
                        return;
                    }
                    $locked->update(['status' => QuoteStatus::Expired]);
                    $this->workflow->record($locked->project, 'quote_expired', "La cotización {$locked->folio} venció", 'Pide a tu asesor una versión actualizada.', null, 'client', metadata: ['quote_id' => $locked->id]);
                    $this->logger->log('cotizacion.vencida', $locked);
                    $count++;
                });
            });

        return $count;
    }

    /** Lleva el proyecto hasta "Cotización" si aún está en una etapa previa. */
    public function advanceToQuoting(Project $project, ?User $actor): void
    {
        $project->refresh();
        if (in_array($project->status, [ProjectStatus::Preparation, ProjectStatus::Measurement], true)) {
            $this->workflow->transition($project, ProjectStatus::Design, $actor, null, false);
            $project->refresh();
        }
        if ($project->status === ProjectStatus::Design) {
            $this->workflow->transition($project, ProjectStatus::Quoting, $actor);
        }
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function writeItemsAndTotals(Quote $quote, array $items, User $actor): void
    {
        $items = array_values(array_filter($items, 'is_array'));
        $totals = $this->calculator->calculate($items, $quote->prices_include_tax, (string) $quote->tax_rate);

        $hasDiscount = Money::of($totals['discount_total'])->isPositive();
        if ($hasDiscount && ! $actor->hasPermission('quotes.authorize_discount')) {
            throw ValidationException::withMessages(['items' => 'Aplicar descuentos requiere autorización. Pide a una persona con permiso que guarde la cotización.']);
        }

        $quote->items()->delete();
        foreach ($items as $index => $item) {
            $line = $totals['items'][$index];
            $quote->items()->create([
                'sort' => $index,
                'code' => isset($item['code']) && $item['code'] !== '' ? mb_substr((string) $item['code'], 0, 40) : null,
                'description' => mb_substr(trim((string) $item['description']), 0, 500),
                'quantity' => $line['quantity'],
                'unit' => mb_substr((string) $item['unit'], 0, 24),
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'line_total' => $line['line_total'],
            ]);
        }

        $quote->update([
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'discount_authorized_by' => $hasDiscount ? $actor->id : null,
        ]);
    }

    private function validUntil(): string
    {
        $days = max(1, (int) Settings::get('commercial.quote_validity_days', 15));

        return now()->addDays($days)->toDateString();
    }

    private function assertProjectOpen(Project $project): void
    {
        if ($project->status === ProjectStatus::Cancelled) {
            throw ValidationException::withMessages(['project' => 'El proyecto está cancelado.']);
        }
    }

    private function assertDesignBelongs(Project $project, mixed $designVersionId): void
    {
        if ($designVersionId !== null && ! DesignVersion::query()->whereKey($designVersionId)->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['design_version_id' => 'La versión de diseño no pertenece a este proyecto.']);
        }
    }
}
