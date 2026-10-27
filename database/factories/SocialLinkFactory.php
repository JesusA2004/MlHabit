<?php

namespace Database\Factories;

use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    protected $model = SocialLink::class;

    public function definition(): array
    {
        return [
            'platform' => 'instagram',
            'username' => '@mrinsight',
            'url' => 'https://instagram.com/mrinsight',
            'is_visible' => true,
            'open_new_tab' => true,
            'order' => 0,
        ];
    }
}
