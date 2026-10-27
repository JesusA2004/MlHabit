<?php

namespace App\Services\Crm;

use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MediaVisibility;
use App\Enums\Permission;
use App\Exceptions\RejectedUploadException;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Crm\LeadSubmittedConfirmation;
use App\Notifications\Crm\NewLeadReceived;
use App\Services\ActivityLogger;
use App\Services\CoverageService;
use App\Services\MediaUploadService;
use App\Support\Folio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Alta de prospectos desde el formulario público /solicitar (y alta manual
 * desde el panel). Todo dentro de una transacción; idempotente por
 * `submission_key` (UUID generado en el navegador por intento de envío):
 * un doble clic o reintento devuelve el mismo prospecto.
 */
class LeadIntakeService
{
    private const IDEMPOTENCY_TTL_HOURS = 24;

    public function __construct(
        private readonly CustomerMatcher $customers,
        private readonly CoverageService $coverage,
        private readonly LeadAssigner $assigner,
        private readonly LeadWorkflow $workflow,
        private readonly MediaUploadService $uploads,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Datos validados (SubmitLeadRequest).
     * @param  array<string, string|null>  $attribution
     * @param  list<UploadedFile>  $photos
     */
    public function submit(array $data, array $attribution, array $photos, string $submissionKey): Lead
    {
        $cacheKey = 'crm.lead_submission.'.hash('sha256', $submissionKey);

        return Cache::lock($cacheKey.'.lock', 30)->block(15, function () use ($cacheKey, $data, $attribution, $photos) {
            $existingId = Cache::get($cacheKey);
            if ($existingId && ($existing = Lead::query()->find($existingId))) {
                return $existing;
            }

            $lead = $this->create($data, $attribution, $photos, LeadSource::WebForm, 'solicitar');
            Cache::put($cacheKey, $lead->id, now()->addHours(self::IDEMPOTENCY_TTL_HOURS));

            return $lead;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $attribution
     * @param  list<UploadedFile>  $photos
     */
    public function create(array $data, array $attribution, array $photos, LeadSource $source, ?string $form, ?User $actor = null): Lead
    {
        // Las fotos se suben antes de la transacción (escritura en disco). Si
        // la transacción falla quedan huérfanas y el purgado de privados las
        // elimina en 24 h.
        $mediaIds = [];
        foreach ($photos as $i => $photo) {
            try {
                $mediaIds[] = $this->uploads->upload($photo, 'privado/prospectos', MediaVisibility::Private)->id;
            } catch (RejectedUploadException $e) {
                // Segunda capa (contenido real del archivo) tras la validación del formulario.
                throw ValidationException::withMessages(["photos.{$i}" => $e->getMessage()]);
            }
        }

        $postalCode = isset($data['postal_code']) ? (string) $data['postal_code'] : null;
        $zone = $this->coverage->zoneForPostalCode($postalCode);

        /** @var Lead $lead */
        $lead = DB::transaction(function () use ($data, $attribution, $mediaIds, $source, $form, $actor, $postalCode, $zone) {
            $folio = Folio::next('L');

            [$customer, $existed] = $this->customers->findOrCreate([
                'first_name' => (string) $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'preferred_contact' => $data['preferred_contact'] ?? null,
            ], "Solicitud {$folio}");

            if ($actor && ! $existed) {
                $customer->update(['created_by' => $actor->id]);
            }

            $previousOpen = $existed
                ? $customer->leads()->open()->latest('id')->get(['id', 'folio', 'assigned_to'])
                : collect();

            $assignee = $this->resolveAssignee($data['assigned_to'] ?? null, $previousOpen->first()?->assigned_to);

            $lead = Lead::query()->create([
                'folio' => $folio,
                'customer_id' => $customer->id,
                'status' => LeadStatus::New,
                'assigned_to' => $assignee?->id,
                'postal_code' => $postalCode,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'coverage_zone_id' => $zone?->id,
                'budget_range' => $data['budget_range'] ?? null,
                'payment_preference' => $data['payment_preference'] ?? null,
                'timeframe' => $data['timeframe'] ?? null,
                'measurements_note' => $data['measurements_note'] ?? null,
                'comments' => $data['comments'] ?? null,
                'source' => $source,
                'form' => $form,
                ...array_intersect_key($attribution, array_flip([
                    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'landing_url', 'device_type',
                ])),
            ]);

            $lead->syncCategories(array_values(array_map('intval', (array) ($data['category_ids'] ?? []))));

            foreach ($mediaIds as $index => $mediaId) {
                $lead->attachments()->create([
                    'media_id' => $mediaId,
                    'role' => Lead::PHOTO_ROLE,
                    'sort' => $index,
                    'attached_by' => $actor?->id,
                ]);
            }

            $this->workflow->record($lead, LeadActivityType::Created, $actor ? "Registrado por {$actor->name}" : 'Solicitud enviada desde el sitio web', $actor, [
                'source' => $source->value,
                'photos' => count($mediaIds) ?: null,
                'existing_customer' => $existed ?: null,
            ]);

            if ($previousOpen->isNotEmpty()) {
                $this->workflow->record($lead, LeadActivityType::DuplicateSubmission, 'El mismo contacto tiene otras solicitudes abiertas: '.$previousOpen->pluck('folio')->implode(', '), $actor, [
                    'lead_ids' => $previousOpen->pluck('id')->all(),
                ]);
            }

            if ($assignee) {
                $this->workflow->record($lead, LeadActivityType::Assigned, "Asignado a {$assignee->name}", $actor, ['to_user_id' => $assignee->id, 'automatic' => $actor === null ?: null]);
            }

            $this->logger->log('prospecto.creado', $lead, [
                'after' => ['folio' => $folio, 'source' => $source->value, 'assigned_to' => $assignee?->id, 'coverage_zone_id' => $zone?->id],
            ]);

            return $lead;
        });

        $this->notify($lead, $actor);

        return $lead;
    }

    /**
     * Responsable: el elegido en el alta manual; si no, quien ya atiende otra
     * solicitud abierta del mismo contacto (continuidad); si no, round-robin.
     */
    private function resolveAssignee(mixed $explicitId, ?int $previousAssigneeId): ?User
    {
        if ($explicitId) {
            return User::query()->where('is_active', true)->find((int) $explicitId);
        }

        if ($previousAssigneeId) {
            $previous = User::query()->where('is_active', true)->find($previousAssigneeId);
            if ($previous) {
                return $previous;
            }
        }

        return $this->assigner->next();
    }

    private function notify(Lead $lead, ?User $actor): void
    {
        try {
            $lead->loadMissing(['customer', 'assignee']);

            $staff = $lead->assignee
                ? collect([$lead->assignee])
                : User::query()->withPermission(Permission::LeadsManage)->get();
            $staff = $staff->reject(fn (User $u) => $u->id === $actor?->id);

            if ($staff->isNotEmpty()) {
                Notification::send($staff, new NewLeadReceived($lead));
            }

            if ($actor === null && $lead->customer->email) {
                Notification::route('mail', $lead->customer->email)->notify(new LeadSubmittedConfirmation($lead));
            }
        } catch (Throwable $e) {
            // Un fallo de correo nunca debe perder la solicitud.
            Log::warning('crm.lead_notification_failed', ['lead' => $lead->id, 'error' => $e->getMessage()]);
        }
    }
}
