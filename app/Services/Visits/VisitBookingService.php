<?php

namespace App\Services\Visits;

use App\Enums\BookingKind;
use App\Enums\PaymentConcept;
use App\Enums\VisitStatus;
use App\Models\Address;
use App\Models\CalendarBooking;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\VisitPolicy;
use App\Payments\PaymentService;
use App\Services\ActivityLogger;
use App\Services\CoverageService;
use App\Services\Scheduling\BookingService;
use App\Services\Scheduling\SlotUnavailableException;
use App\Support\Folio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reserva de una visita técnica (checkout público y alta desde el panel).
 *
 * En UNA transacción: re-valida política y cobertura → bloquea la zona
 * (cupo diario) → aparta el horario (BookingService::reserve con holdUntil)
 * en el técnico menos cargado → crea la visita con tarifa/crédito
 * CONGELADOS → crea la intención de cobro (concept visit_fee).
 * Idempotente por la clave del cliente: la misma clave devuelve la misma visita.
 */
class VisitBookingService
{
    public function __construct(
        private readonly CoverageService $coverage,
        private readonly VisitAvailability $availability,
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
        private readonly LeadBridge $leads,
        private readonly ActivityLogger $audit,
        private readonly VisitPaymentHandler $handler,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Datos validados (CP, fecha, hora, contacto, domicilio, notas).
     *
     * @throws VisitBookingException|SlotUnavailableException
     */
    public function book(
        array $data,
        string $idempotencyKey,
        ?Lead $lead = null,
        ?User $actor = null,
        ?CarbonImmutable $holdUntil = null,
        ?int $technicianId = null,
    ): SiteVisit {
        $existing = SiteVisit::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $policy = VisitPolicy::current();
        if ($policy === null) {
            throw new VisitBookingException('Por ahora agenda tu visita con un asesor.', VisitBookingException::NO_POLICY);
        }

        $postalCode = (string) $data['postal_code'];
        $zone = $this->coverage->zoneForPostalCode($postalCode);
        if ($zone === null || ! $this->coverage->acceptsVisits($postalCode)) {
            throw new VisitBookingException('Todavía no damos visitas técnicas en ese código postal.', VisitBookingException::NO_COVERAGE);
        }

        $startsAt = CarbonImmutable::parse($data['date'].' '.$data['start']);
        $endsAt = $startsAt->addMinutes($policy->duration_minutes);

        if (! $this->availability->withinWindow($policy, $startsAt)) {
            throw new SlotUnavailableException('Ese horario ya no está disponible. Elige otro, por favor.');
        }

        $holdUntil ??= CarbonImmutable::now()->addMinutes(max(1, $policy->hold_minutes));

        return DB::transaction(function () use ($data, $idempotencyKey, $lead, $actor, $holdUntil, $technicianId, $policy, $zone, $postalCode, $startsAt, $endsAt) {
            // Serializa reservas de la misma zona (cupo diario).
            CoverageZone::query()->whereKey($zone->id)->lockForUpdate()->first();

            $again = SiteVisit::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($again !== null) {
                return $again;
            }

            if ($this->availability->zoneCapacityReached($zone, $startsAt)) {
                throw new SlotUnavailableException('Ya no hay cupo de visitas para ese día. Elige otra fecha, por favor.');
            }

            $customer = $lead !== null ? $this->completeLeadCustomer($lead, $data) : $this->leads->resolveCustomer($this->contact($data));

            $address = Address::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'postal_code' => $postalCode,
                'street' => trim((string) $data['street']),
                'exterior_number' => trim((string) $data['exterior_number']),
                'interior_number' => isset($data['interior_number']) && $data['interior_number'] !== '' ? trim((string) $data['interior_number']) : null,
            ], [
                'neighborhood' => trim((string) $data['neighborhood']),
                'municipality' => trim((string) $data['municipality']),
                'state' => trim((string) $data['state']),
                'references' => $data['references'] ?? null,
                'coverage_zone_id' => $zone->id,
            ]);

            $fee = $policy->feeMoney();
            $credit = $policy->creditAmountFor($fee);
            $free = ! $fee->isPositive();

            $visit = SiteVisit::query()->create([
                'folio' => Folio::next('V'),
                'lead_id' => $lead?->id,
                'customer_id' => $customer->id,
                'address_id' => $address->id,
                'coverage_zone_id' => $zone->id,
                'visit_policy_id' => $policy->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => VisitStatus::PendingPayment,
                'fee_amount' => $fee->amount,
                'currency' => $policy->currency,
                'is_creditable' => $policy->is_creditable && $credit->isPositive(),
                'credit_amount' => $credit->amount,
                'hold_expires_at' => $free ? null : $holdUntil,
                'idempotency_key' => $idempotencyKey,
                'access_notes' => $data['access_notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $booking = $this->reserveBestTechnician($zone, $policy, $startsAt, $endsAt, $free ? null : $holdUntil, $visit, $technicianId, $actor !== null);
            $visit->forceFill(['technician_id' => $booking->user_id])->save();

            if ($lead === null) {
                $lead = $this->leads->createForVisit(array_merge($data, ['postal_code' => $postalCode]));
                if ($lead !== null) {
                    $visit->forceFill(['lead_id' => $lead->id])->save();
                }
            }

            $this->audit->log('visita.apartada', $visit, [
                'folio' => $visit->folio,
                'inicio' => $startsAt->toIso8601String(),
                'tecnico' => $booking->user_id,
                'politica' => $policy->version,
                'tarifa' => $fee->amount,
                'apartado_hasta' => $free ? null : $holdUntil->toIso8601String(),
            ]);

            if ($free) {
                $this->handler->scheduleWithoutPayment($visit, $actor);

                return $visit->fresh() ?? $visit;
            }

            $this->payments->createIntent(
                $customer,
                PaymentConcept::VisitFee,
                $fee,
                "Visita técnica a domicilio · {$visit->folio}",
                ['site_visit_id' => $visit->id],
                'visit:'.$visit->uuid,
            );

            return $visit;
        });
    }

    /**
     * Aparta el horario en el técnico menos cargado que lo tenga libre.
     *
     * @throws SlotUnavailableException
     */
    public function reserveBestTechnician(
        CoverageZone $zone,
        VisitPolicy $policy,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?CarbonImmutable $holdUntil,
        SiteVisit $visit,
        ?int $technicianId = null,
        bool $staffOverride = false,
    ): CalendarBooking {
        $zoneTechnicians = array_values($this->availability->technicians($zone)->pluck('id')->map(fn ($id) => (int) $id)->all());

        if ($technicianId !== null) {
            if (! in_array($technicianId, $zoneTechnicians, true)) {
                throw new SlotUnavailableException('Ese técnico no atiende la zona de la visita.');
            }
            $candidates = [$technicianId];
        } else {
            $candidates = $this->availability->rankByLoad($zoneTechnicians, $startsAt);
        }

        $buffer = $this->availability->bufferMinutes($zone, $policy);

        foreach ($candidates as $id) {
            /** @var User|null $technician */
            $technician = User::query()->find($id);
            if ($technician === null) {
                continue;
            }

            try {
                return $this->bookings->reserve(
                    $technician,
                    BookingKind::Visit,
                    $startsAt,
                    $endsAt,
                    $buffer,
                    $holdUntil,
                    $visit,
                    // El personal puede elegir un técnico específico fuera de su disponibilidad recurrente.
                    checkAvailability: ! ($staffOverride && $technicianId !== null),
                );
            } catch (SlotUnavailableException) {
                continue;
            }
        }

        throw new SlotUnavailableException;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{first_name: string, last_name: string|null, email: string|null, phone: string|null}
     */
    private function contact(array $data): array
    {
        return [
            'first_name' => trim((string) $data['first_name']),
            'last_name' => isset($data['last_name']) ? (trim((string) $data['last_name']) ?: null) : null,
            'email' => isset($data['email']) ? (mb_strtolower(trim((string) $data['email'])) ?: null) : null,
            'phone' => isset($data['phone']) ? (preg_replace('/\D/', '', (string) $data['phone']) ?: null) : null,
        ];
    }

    /**
     * Cliente del prospecto: solo completa datos vacíos (nunca sobrescribe).
     *
     * @param  array<string, mixed>  $data
     */
    private function completeLeadCustomer(Lead $lead, array $data): Customer
    {
        /** @var Customer $customer */
        $customer = Customer::query()->withTrashed()->findOrFail($lead->customer_id);
        $contact = $this->contact($data);
        $fill = [];

        foreach (['email', 'phone', 'last_name'] as $field) {
            if (($customer->getAttribute($field) === null || $customer->getAttribute($field) === '') && $contact[$field] !== null) {
                $fill[$field] = $contact[$field];
            }
        }

        if ($fill !== []) {
            $customer->update($fill);
        }

        return $customer;
    }
}
