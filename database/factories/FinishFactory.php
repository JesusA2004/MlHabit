<?php

namespace Database\Factories;

use App\Models\Finish;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Finish>
 */
class FinishFactory extends Factory
{
    protected $model = Finish::class;

    public function definition(): array
    {
        $name = 'Acabado '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'material' => 'Melamina',
            'color_hex' => fake()->hexColor(),
            'is_active' => true,
            'sort' => 0,
        ];
    }
}
