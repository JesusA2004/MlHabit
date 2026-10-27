<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Address;
use App\Models\CoverageZone;
use App\Models\Customer;
use App\Models\SiteVisit;
use App\Models\VisitPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SiteVisit> */
class SiteVisitFactory extends Factory
{
    protected $model = SiteVisit::class;

    public function definition(): array
    {
        $start = now()->addDays(3)->setTime(10, 0);

        return [
            'folio' => fn () => 'MLH-V-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'address_id' => fn (array $attributes) => Address::factory()->create(['customer_id' => $attributes['customer_id']])->id,
            'coverage_zone_id' => CoverageZone::factory(),
            'visit_policy_id' => VisitPolicy::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(90),
            'status' => VisitStatus::Scheduled,
            'fee_amount' => '1000.00',
            'currency' => 'MXN',
            'is_creditable' => false,
            'credit_amount' => '0.00',
            'idempotency_key' => fn () => (string) Str::uuid(),
        ];
    }
}
