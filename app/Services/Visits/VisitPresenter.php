<?php

namespace App\Services\Visits;

use App\Models\Address;
use App\Models\SiteVisit;
use App\Models\VisitPolicy;
use App\Notifications\Visits\VisitText;
use App\Support\Money;

/** Arreglos explícitos de visitas/políticas para Inertia. */
final class VisitPresenter
{
    /** @return array<string, mixed> */
    public static function policy(VisitPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'version' => $policy->version,
            'fee' => $policy->fee_amount,
            'currency' => $policy->currency,
            'isCreditable' => $policy->is_creditable,
            'creditType' => $policy->credit_type?->value,
            'creditValue' => $policy->credit_value,
            'creditAmount' => $policy->creditAmountFor($policy->feeMoney())->amount,
            'creditValidityDays' => $policy->credit_validity_days,
            'creditConditions' => $policy->credit_conditions,
            'durationMinutes' => $policy->duration_minutes,
            'bufferMinutes' => $policy->buffer_minutes,
            'holdMinutes' => $policy->hold_minutes,
            'minNoticeHours' => $policy->min_notice_hours,
            'bookingWindowDays' => $policy->booking_window_days,
            'scope' => $policy->scope,
            'cancellationPolicy' => $policy->cancellation_policy,
            'reschedulePolicy' => $policy->reschedule_policy,
            'isActive' => $policy->is_active,
            'activatedAt' => $policy->activated_at?->toIso8601String(),
            'createdAt' => $policy->created_at?->toIso8601String(),
        ];
    }

    /**
     * Fila de listado (requiere customer, zone, technician cargados).
     *
     * @return array<string, mixed>
     */
    public static function row(SiteVisit $visit): array
    {
        return [
            'uuid' => $visit->uuid,
            'folio' => $visit->folio,
            'customer' => $visit->customer->full_name,
            'zone' => $visit->zone->name,
            'technician' => $visit->technician?->name,
            'startsAt' => $visit->starts_at->toIso8601String(),
            'endsAt' => $visit->ends_at->toIso8601String(),
            'status' => $visit->status->toArray(),
            'fee' => $visit->fee_amount,
            'attention' => $visit->attention_reason,
            'holdExpiresAt' => $visit->holdIsActive() ? $visit->hold_expires_at?->toIso8601String() : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function address(Address $address): array
    {
        return [
            'oneLine' => $address->one_line,
            'street' => $address->street,
            'exteriorNumber' => $address->exterior_number,
            'interiorNumber' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'postalCode' => $address->postal_code,
            'municipality' => $address->municipality,
            'state' => $address->state,
            'references' => $address->references,
            'mapUrl' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address->one_line),
        ];
    }

    /**
     * Resumen para el cliente (checkout/estado): sin notas internas ni datos
     * del técnico más allá del nombre de pila.
     *
     * @return array<string, mixed>
     */
    public static function forCustomer(SiteVisit $visit): array
    {
        return [
            'uuid' => $visit->uuid,
            'folio' => $visit->folio,
            'status' => $visit->status->toArray(),
            'startsAt' => $visit->starts_at->toIso8601String(),
            'endsAt' => $visit->ends_at->toIso8601String(),
            'whenText' => VisitText::when($visit),
            'holdExpiresAt' => $visit->holdIsActive() ? $visit->hold_expires_at?->toIso8601String() : null,
            'address' => $visit->address->one_line,
            'zone' => $visit->zone->name,
            'technicianFirstName' => $visit->technician ? VisitText::firstName($visit->technician->name) : null,
            'fee' => $visit->fee_amount,
            'currency' => $visit->currency,
            'isCreditable' => $visit->is_creditable && Money::of($visit->credit_amount)->isPositive(),
            'creditAmount' => $visit->credit_amount,
            'creditValidityDays' => $visit->policy->credit_validity_days,
            'cancellationPolicy' => $visit->policy->cancellation_policy,
            'reschedulePolicy' => $visit->policy->reschedule_policy,
            'scope' => $visit->policy->scope,
            'needsReschedule' => $visit->attention_reason !== null,
            'customerFirstName' => $visit->customer->first_name,
        ];
    }
}
