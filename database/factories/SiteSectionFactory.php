<?php

namespace Database\Factories;

use App\Cms\Layout\LegacyLayouts;
use App\Enums\SiteSectionType;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteSection>
 */
class SiteSectionFactory extends Factory
{
    protected $model = SiteSection::class;

    public function definition(): array
    {
        $content = SiteSectionType::Hero->schema()->defaults();

        return [
            'key' => (string) Str::uuid(),
            'page' => 'home',
            'type' => SiteSectionType::Hero,
            'sort' => 10,
            'is_visible' => true,
            'content' => $content,
            'layout' => LegacyLayouts::for(SiteSectionType::Hero, $content),
            'layout_version' => 1,
        ];
    }

    public function type(SiteSectionType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'content' => $type->schema()->defaults(),
            'layout' => LegacyLayouts::for($type, $type->schema()->defaults()),
        ]);
    }

    /** Sección anterior al editor visual (sin lienzo guardado). */
    public function legacy(): static
    {
        return $this->state(fn () => ['layout' => null]);
    }
}
