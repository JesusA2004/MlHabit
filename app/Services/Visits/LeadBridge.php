<?php

namespace App\Services\Visits;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Crm\CustomerMatcher;
use App\Services\Crm\LeadIntakeService;
use App\Services\Crm\LeadWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puente con el módulo crm (dueño de Lead/LeadWorkflow). Todas las llamadas
 * son de "mejor esfuerzo": un fallo del CRM nunca bloquea una reserva ni la
 * confirmación de un pago (se registra en el log para revisión).
 */
class LeadBridge
{
    public function available(): bool
    {
        return class_exists(Lead::class) && class_exists(LeadWorkflow::class) && class_exists(LeadStatus::class);
    }

    /**
     * Cliente del checkout abierto: deduplica con el CustomerMatcher del CRM
     * (correo/teléfono) o crea uno nuevo.
     *
     * @param  array{first_name: string, last_name?: string|null, email?: string|null, phone?: string|null}  $data
     */
    public function resolveCustomer(array $data): Customer
    {
        if (class_exists(CustomerMatcher::class)) {
            [$customer] = app(CustomerMatcher::class)->findOrCreate($data, 'Agenda de visita');

            return $customer;
        }

        return Customer::query()->create([
            'first_name' => trim($data['first_name']),
            'last_name' => $data['last_name'] ?? null,
            'email' => isset($data['email']) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => isset($data['phone']) ? (preg_replace('/\D/', '', $data['phone']) ?: null) : null,
            'privacy_accepted_at' => now(),
        ]);
    }

    /**
     * Prospecto para una visita agendada desde el flujo abierto (origen
     * web_form, etapa visit_pending).
     *
     * @param  array<string, mixed>  $data
     */
    public function createForVisit(array $data): ?Lead
    {
        if (! $this->available() || ! class_exists(LeadIntakeService::class)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($data) {
                $lead = app(LeadIntakeService::class)->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'city' => $data['municipality'] ?? null,
                    'state' => $data['state'] ?? null,
                    'comments' => 'Agendó una visita técnica desde el sitio web.',
                ], [], [], LeadSource::WebForm, 'agendar-visita');

                return app(LeadWorkflow::class)->transition($lead, LeadStatus::VisitPending, null, 'Visita técnica apartada desde el sitio web', true);
            });
        } catch (Throwable $e) {
            Log::warning('visits.lead_create_failed', ['error' => class_basename($e)]);

            return null;
        }
    }

    /** Avanza la etapa del prospecto (solo hacia adelante: LeadWorkflow force). */
    public function advance(?Lead $lead, LeadStatus $to, ?User $actor = null, ?string $note = null): void
    {
        if ($lead === null || ! $this->available()) {
            return;
        }

        try {
            app(LeadWorkflow::class)->transition($lead, $to, $actor, $note, true);
        } catch (Throwable $e) {
            Log::warning('visits.lead_transition_failed', ['lead' => $lead->id, 'to' => $to->value, 'error' => class_basename($e)]);
        }
    }
}
