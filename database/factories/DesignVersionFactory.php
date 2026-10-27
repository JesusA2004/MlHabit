<?php

namespace Database\Factories;

use App\Enums\DesignStatus;
use App\Models\DesignVersion;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignVersion>
 */
class DesignVersionFactory extends Factory
{
    protected $model = DesignVersion::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => 1,
            'title' => 'Propuesta de distribución',
            'status' => DesignStatus::Draft,
        ];
    }

    public function status(DesignStatus $status): static
    {
        return $this->state(fn () => ['status' => $status, 'sent_at' => $status === DesignStatus::Draft ? null : now()]);
    }
}
