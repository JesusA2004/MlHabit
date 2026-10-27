<?php

namespace Database\Factories;

use App\Enums\VisitCreditStatus;
use App\Models\Payment;
use App\Models\SiteVisit;
use App\Models\VisitCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisitCredit> */
class VisitCreditFactory extends Factory
{
    protected $model = VisitCredit::class;

    public function definition(): array
    {
        return [
            'site_visit_id' => SiteVisit::factory(),
            'payment_id' => Payment::factory()->paid(),
            'amount' => '1000.00',
            'status' => VisitCreditStatus::Available,
            'expires_on' => now()->addDays(60)->toDateString(),
        ];
    }
}
