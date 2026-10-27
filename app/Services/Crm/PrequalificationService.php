<?php

namespace App\Services\Crm;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\MediaVisibility;
use App\Enums\Permission;
use App\Enums\PrequalificationStatus;
use App\Exceptions\RejectedUploadException;
use App\Models\Lead;
use App\Models\Prequalification;
use App\Models\User;
use App\Notifications\Crm\CustomerNotifier;
use App\Notifications\Crm\PrequalificationRequested;
use App\Notifications\Crm\PrequalificationReviewed;
use App\Notifications\Crm\PrequalificationSubmitted;
use App\Services\ActivityLogger;
use App\Services\MediaUploadService;
use App\Support\Folio;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Flujo de precalificación con revisión 100 % manual:
 * draft → submitted → in_review → (info_requested ↔ submitted) →
 * prequalified | not_prequalified. Nunca hay decisiones automáticas.
 * Cada paso queda en la bitácora del prospecto y en auditoría.
 */
class PrequalificationService
{
    public const MAX_DOCUMENTS = 8;

    public function __construct(
        private readonly LeadWorkflow $workflow,
        private readonly MediaUploadService $uploads,
        private readonly ActivityLogger $logger,
    ) {}

    /** Precalificación abierta (no retirada) del prospecto, si existe. */
    public function current(Lead $lead): ?Prequalification
    {
        return $lead->prequalifications()
            ->where('status', '!=', PrequalificationStatus::Withdrawn->value)
            ->latest('id')
            ->first();
    }

    /** El equipo inicia la precalificación y (opcional) envía el enlace al cliente. */
    public function start(Lead $lead, User $actor, bool $sendLink = true, ?string $message = null): Prequalification
    {
        $prequalification = DB::transaction(function () use ($lead, $actor) {
            Lead::query()->whereKey($lead->id)->lockForUpdate()->first();

            if ($existing = $this->current($lead)) {
                return $existing;
            }

            $p = Prequalification::query()->create([
                'folio' => Folio::next('Q'),
                'lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'status' => PrequalificationStatus::Draft,
            ]);

            $this->workflow->transition($lead, LeadStatus::Prequalification, $actor, 'Se inició la precalificación', force: true);
            $this->workflow->record($lead, LeadActivityType::PrequalificationUpdated, "Precalificación {$p->folio} iniciada", $actor, ['prequalification_id' => $p->id]);
            $this->logger->log('precalificacion.iniciada', $p);

            return $p;
        });

        if ($sendLink && $prequalification->status->acceptsCustomerInput()) {
            $this->safely(fn () => CustomerNotifier::send($lead->customer, new PrequalificationRequested($prequalification, $message)));
        }

        return $prequalification;
    }

    /**
     * El cliente envía su información (o la completa tras `info_requested`).
     *
     * @param  array{requested_amount?: string|null, preferred_term_months?: int|null, income_range?: string|null, employment_type?: string|null, applicant_notes?: string|null}  $data
     * @param  list<UploadedFile>  $documents
     */
    public function submitFromCustomer(Lead $lead, array $data, array $documents): Prequalification
    {
        $mediaIds = [];
        foreach ($documents as $i => $file) {
            try {
                $mediaIds[] = $this->uploads->upload($file, 'privado/precalificaciones', MediaVisibility::Private)->id;
            } catch (RejectedUploadException $e) {
                throw ValidationException::withMessages(["documents.{$i}" => $e->getMessage()]);
            }
        }

        $p = DB::transaction(function () use ($lead, $data, $mediaIds) {
            Lead::query()->whereKey($lead->id)->lockForUpdate()->first();

            $p = $this->current($lead);
            if ($p === null) {
                $p = Prequalification::query()->create([
                    'folio' => Folio::next('Q'),
                    'lead_id' => $lead->id,
                    'customer_id' => $lead->customer_id,
                    'status' => PrequalificationStatus::Draft,
                ]);
            }

            if (! $p->status->acceptsCustomerInput()) {
                throw ValidationException::withMessages(['form' => 'Ya recibimos tu información y está en revisión. Te contactaremos pronto.']);
            }

            $existingDocs = $p->attachments()->where('role', Prequalification::DOCUMENT_ROLE)->count();
            if ($existingDocs + count($mediaIds) > self::MAX_DOCUMENTS) {
                throw ValidationException::withMessages(['documents' => 'Puedes adjuntar hasta '.self::MAX_DOCUMENTS.' documentos en total.']);
            }

            $before = $p->status;
            $p->update([
                'requested_amount' => isset($data['requested_amount']) && $data['requested_amount'] !== '' ? Money::of((string) $data['requested_amount'])->amount : null,
                'preferred_term_months' => $data['preferred_term_months'] ?? null,
                'income_range' => $data['income_range'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'applicant_notes' => $data['applicant_notes'] ?? null,
                'status' => PrequalificationStatus::Submitted,
                'submitted_at' => now(),
            ]);

            foreach ($mediaIds as $i => $mediaId) {
                $p->attachments()->create([
                    'media_id' => $mediaId,
                    'role' => Prequalification::DOCUMENT_ROLE,
                    'sort' => $existingDocs + $i,
                ]);
            }

            $this->workflow->transition($lead, LeadStatus::Prequalification, null, null, force: true);
            $this->workflow->record($lead, LeadActivityType::PrequalificationUpdated, 'El cliente envió su información de precalificación'.($mediaIds ? ' ('.count($mediaIds).' documentos)' : ''), null, [
                'prequalification_id' => $p->id,
            ]);
            // No se registran montos ni ingresos en la bitácora (dato sensible).
            $this->logger->log('precalificacion.enviada', $p, [
                'before' => ['status' => $before->value],
                'after' => ['status' => PrequalificationStatus::Submitted->value],
                'documents' => count($mediaIds),
            ]);

            return $p;
        });

        $this->safely(function () use ($p) {
            $reviewers = User::query()->withPermission(Permission::PrequalificationReview)->get();
            if ($reviewers->isNotEmpty()) {
                Notification::send($reviewers, new PrequalificationSubmitted($p));
            }
        });

        return $p;
    }

    /**
     * Transición del equipo de revisión. Decidir (precalificado / no) exige
     * notas de decisión.
     */
    public function transition(Prequalification $prequalification, PrequalificationStatus $to, User $actor, ?string $notes = null, bool $notifyCustomer = true): Prequalification
    {
        $p = DB::transaction(function () use ($prequalification, $to, $actor, $notes) {
            /** @var Prequalification $locked */
            $locked = Prequalification::query()->whereKey($prequalification->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if ($from === $to) {
                return $locked;
            }
            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de «{$from->label()}» a «{$to->label()}»."]);
            }
            if (($to->isDecided() || $to === PrequalificationStatus::InfoRequested) && blank($notes)) {
                throw ValidationException::withMessages(['notes' => $to === PrequalificationStatus::InfoRequested
                    ? 'Indica qué información necesitas del cliente.'
                    : 'Escribe las notas de la decisión; quedan en el expediente.']);
            }

            $changes = ['status' => $to];
            if ($to === PrequalificationStatus::InReview) {
                $changes['reviewer_id'] = $actor->id;
            }
            if ($to->isDecided()) {
                $changes['reviewer_id'] = $actor->id;
                $changes['reviewed_at'] = now();
                $changes['decision_notes'] = $notes;
            }
            if ($to === PrequalificationStatus::InfoRequested) {
                $changes['decision_notes'] = $notes;
            }
            $locked->update($changes);

            $this->workflow->record($locked->lead, LeadActivityType::PrequalificationUpdated, "Precalificación: {$to->label()}", $actor, [
                'prequalification_id' => $locked->id,
            ]);
            $this->logger->log('precalificacion.estado', $locked, [
                'before' => ['status' => $from->value],
                'after' => ['status' => $to->value],
            ]);

            return $locked;
        });

        if ($notifyCustomer && $p->wasChanged('status')) {
            $this->safely(function () use ($p, $notes) {
                $p->loadMissing('customer');
                if ($p->status === PrequalificationStatus::InfoRequested) {
                    CustomerNotifier::send($p->customer, new PrequalificationRequested($p, $notes));
                } elseif ($p->status->isDecided()) {
                    CustomerNotifier::send($p->customer, new PrequalificationReviewed($p));
                }
            });
        }

        return $p;
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('crm.prequalification_notification_failed', ['error' => $e->getMessage()]);
        }
    }
}
