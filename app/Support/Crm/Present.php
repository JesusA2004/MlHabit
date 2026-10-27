<?php

namespace App\Support\Crm;

use App\Models\Address;
use App\Models\Consultation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Media;
use App\Models\Prequalification;
use App\Models\User;
use App\Services\CoverageService;
use Illuminate\Support\Facades\DB;

/**
 * Arreglos explícitos para props de Inertia (nunca modelos completos).
 */
final class Present
{
    /** @return array{id: int, name: string}|null */
    public static function user(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }

    /** @return array<string, mixed> */
    public static function customerMini(Customer $customer): array
    {
        return [
            'uuid' => $customer->uuid,
            'name' => $customer->full_name,
            'firstName' => $customer->first_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'preferredContact' => $customer->preferred_contact,
            'hasPortal' => $customer->user_id !== null,
        ];
    }

    /**
     * @param  list<string>|null  $categories
     * @return array<string, mixed>
     */
    public static function leadRow(Lead $lead, ?array $categories = null): array
    {
        return [
            'uuid' => $lead->uuid,
            'folio' => $lead->folio,
            'status' => Options::one($lead->status),
            'source' => Options::one($lead->source),
            'customer' => self::customerMini($lead->customer),
            'assignee' => self::user($lead->assignee),
            'postalCode' => $lead->postal_code,
            'city' => $lead->city,
            'covered' => $lead->coverage_zone_id !== null,
            'budget' => Options::maybe($lead->budget_range),
            'nextFollowUpAt' => $lead->next_follow_up_at?->toIso8601String(),
            'firstContactedAt' => $lead->first_contacted_at?->toIso8601String(),
            'createdAt' => $lead->created_at?->toIso8601String(),
            'categories' => $categories ?? array_column($lead->categorySummaries(), 'name'),
        ];
    }

    /**
     * Nombres de categorías por prospecto en una sola consulta.
     *
     * @param  list<int>  $leadIds
     * @return array<int, list<string>>
     */
    public static function categoriesFor(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        return DB::table('lead_product_category')
            ->join('product_categories', 'product_categories.id', '=', 'lead_product_category.product_category_id')
            ->whereIn('lead_product_category.lead_id', $leadIds)
            ->orderBy('product_categories.sort')
            ->get(['lead_product_category.lead_id', 'product_categories.name'])
            ->groupBy('lead_id')
            ->map(fn ($rows) => array_values($rows->pluck('name')->map(fn ($n) => (string) $n)->all()))
            ->all();
    }

    /** @return array<string, mixed> */
    public static function activity(LeadActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => Options::one($activity->type),
            'from' => Options::maybe($activity->from_status),
            'to' => Options::maybe($activity->to_status),
            'body' => $activity->body,
            'actor' => $activity->user?->name,
            'createdAt' => $activity->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function consultation(Consultation $c, bool $withLead = false): array
    {
        $row = [
            'uuid' => $c->uuid,
            'folio' => $c->folio,
            'startsAt' => $c->starts_at->toIso8601String(),
            'endsAt' => $c->ends_at->toIso8601String(),
            'status' => Options::one($c->status),
            'channel' => Options::one($c->channel),
            'outcome' => Options::maybe($c->outcome),
            'advisor' => self::user($c->advisor),
            'meetingUrl' => $c->meeting_url,
            'price' => $c->price,
            'isActive' => $c->status->isActive(),
        ];

        if ($withLead) {
            $row['lead'] = [
                'uuid' => $c->lead->uuid,
                'folio' => $c->lead->folio,
                'customer' => self::customerMini($c->lead->customer),
            ];
        }

        return $row;
    }

    /** @return array<string, mixed> */
    public static function prequalificationRow(Prequalification $p): array
    {
        return [
            'uuid' => $p->uuid,
            'folio' => $p->folio,
            'status' => Options::one($p->status),
            'customer' => ['uuid' => $p->customer->uuid, 'name' => $p->customer->full_name],
            'lead' => ['uuid' => $p->lead->uuid, 'folio' => $p->lead->folio],
            'reviewer' => self::user($p->reviewer),
            'submittedAt' => $p->submitted_at?->toIso8601String(),
            'reviewedAt' => $p->reviewed_at?->toIso8601String(),
            'createdAt' => $p->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function address(Address $address, CoverageService $coverage): array
    {
        $zone = $coverage->zoneForPostalCode($address->postal_code);

        return [
            'id' => $address->id,
            'label' => $address->label,
            'street' => $address->street,
            'exteriorNumber' => $address->exterior_number,
            'interiorNumber' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'postalCode' => $address->postal_code,
            'municipality' => $address->municipality,
            'state' => $address->state,
            'references' => $address->references,
            'oneLine' => $address->one_line,
            'coverage' => [
                'covered' => $zone !== null,
                'zone' => $zone?->name,
                'visitsEnabled' => $coverage->acceptsVisits($address->postal_code),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function media(Media $media): array
    {
        return [
            'uuid' => $media->uuid,
            'name' => $media->original_filename,
            'mime' => $media->mime_type,
            'size' => $media->size,
            'isImage' => str_starts_with($media->mime_type, 'image/'),
            'url' => CrmLinks::privateMedia($media),
            'thumbUrl' => str_starts_with($media->mime_type, 'image/') ? CrmLinks::privateMedia($media, 'thumbnail') : null,
            'createdAt' => $media->created_at?->toIso8601String(),
        ];
    }
}
