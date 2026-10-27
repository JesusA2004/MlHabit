<?php

namespace Database\Factories;

use App\Enums\FaqTopic;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'topic' => FaqTopic::General,
            'question' => rtrim(fake()->sentence(8), '.').'?',
            'answer' => fake()->paragraph(),
            'sort' => 0,
            'is_published' => true,
        ];
    }
}
