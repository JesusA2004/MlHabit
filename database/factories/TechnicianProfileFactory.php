<?php

namespace Database\Factories;

use App\Enums\TechnicianStatus;
use App\Enums\UserRole;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianProfile>
 */
class TechnicianProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Technician),
            'status' => TechnicianStatus::Active,
            'does_visits' => true,
            'does_installations' => true,
        ];
    }
}
