<?php

namespace Database\Factories;

use App\Models\SitePageDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePageDraft>
 */
class SitePageDraftFactory extends Factory
{
    protected $model = SitePageDraft::class;

    public function definition(): array
    {
        return [
            'page' => 'home',
            'snapshot' => ['sections' => []],
            'revision' => 1,
        ];
    }
}
