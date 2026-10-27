<?php

namespace Database\Factories;

use App\Enums\SiteSectionType;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSection>
 */
class SiteSectionFactory extends Factory
{
    protected $model = SiteSection::class;

    public function definition(): array
    {
        return [
            'page' => 'home',
            'type' => SiteSectionType::Hero,
            'sort' => 10,
            'is_visible' => true,
            'content' => SiteSectionType::Hero->schema()->defaults(),
        ];
    }

    public function type(SiteSectionType $type): static
    {
        return $this->state(fn () => ['type' => $type, 'content' => $type->schema()->defaults()]);
    }
}
