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
        $user = fake()->userName();

        return [
            'platform' => 'instagram',
            'username' => '@'.$user,
            'url' => 'https://instagram.com/'.$user,
            'is_visible' => true,
            'open_new_tab' => true,
            'order' => 0,
        ];
    }
}
