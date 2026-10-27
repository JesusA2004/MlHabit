<?php

namespace Database\Factories;

use App\Models\SitePageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePageVersion>
 */
class SitePageVersionFactory extends Factory
{
    protected $model = SitePageVersion::class;

    public function definition(): array
    {
        return [
            'page' => 'home',
            'number' => 1,
            'snapshot' => ['sections' => []],
            'note' => null,
            'published_at' => now(),
        ];
    }
}
