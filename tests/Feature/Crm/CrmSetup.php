<?php

namespace Tests\Feature\Crm;

use App\Enums\BookingKind;
use App\Enums\UserRole;
use App\Models\AvailabilityRule;
use App\Models\Consultation;
use App\Models\CoveragePostalCode;
use App\Models\CoverageZone;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Datos de apoyo para las pruebas del módulo CRM (solo factories / filas mínimas). */
final class CrmSetup
{
    public static function category(string $name = 'Cocinas integrales', bool $published = true): int
    {
        return (int) DB::table('product_categories')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_published' => $published,
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Marca pagada la intención de cobro de una asesoría (como si Openpay
     * confirmara el cargo): dispara PaymentConfirmed → asesoría confirmada.
     */
    public static function pay(Consultation $consultation): Consultation
    {
        $payment = Payment::query()->findOrFail($consultation->fresh()?->payment_id);
        app(PaymentService::class)->markPaid($payment, null, 'test');

        return $consultation->fresh() ?? $consultation;
    }

    public static function zone(string $postalCode = '06700', bool $withTechnician = true): CoverageZone
    {
        $zone = CoverageZone::factory()->create(['visits_enabled' => true]);
        CoveragePostalCode::query()->create(['coverage_zone_id' => $zone->id, 'postal_code' => $postalCode]);
        if ($withTechnician) {
            $zone->technicians()->attach(User::factory()->role(UserRole::Technician)->create()->refresh()->id);
        }

        return $zone;
    }

    /**
     * Asesor con horario de asesorías en los días indicados (0 = domingo).
     *
     * @param  list<int>  $weekdays
     */
    public static function advisor(array $weekdays = [1, 2, 3, 4, 5], string $from = '09:00', string $until = '13:00', UserRole $role = UserRole::Sales): User
    {
        $user = User::factory()->role($role)->create()->refresh();
        foreach ($weekdays as $weekday) {
            AvailabilityRule::query()->create([
                'user_id' => $user->id,
                'kind' => BookingKind::Consultation,
                'weekday' => $weekday,
                'starts_at' => $from.':00',
                'ends_at' => $until.':00',
                'is_active' => true,
            ]);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    public static function leadPayload(int $categoryId, array $overrides = []): array
    {
        return array_merge([
            'submission_key' => (string) Str::uuid(),
            'category_ids' => [$categoryId],
            'postal_code' => '06700',
            'city' => 'Ciudad de México',
            'state' => 'Ciudad de México',
            'budget_range' => '100k_200k',
            'payment_preference' => 'plazos',
            'timeframe' => 'one_three_months',
            'first_name' => 'Mariana',
            'last_name' => 'López',
            'phone' => '55 1234 5678',
            'email' => 'Mariana@Example.com',
            'preferred_contact' => 'whatsapp',
            'comments' => 'Quiero renovar la cocina.',
            'privacy' => '1',
        ], $overrides);
    }
}
