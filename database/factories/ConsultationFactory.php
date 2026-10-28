<?php

namespace Database\Factories;

use App\Enums\ConsultationChannel;
use App\Enums\ConsultationStatus;
use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\Lead;
use App\Models\User;
use App\Support\Folio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(2)->setTime(10, 0);

        return [
            'folio' => Folio::next('A'),
            'lead_id' => Lead::factory(),
            'advisor_id' => User::factory()->role(UserRole::Sales),
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(30),
            'status' => ConsultationStatus::Confirmed,
            'channel' => ConsultationChannel::Video,
            'price' => '500.00',
        ];
    }
}
