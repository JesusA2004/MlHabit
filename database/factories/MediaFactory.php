<?php

namespace Database\Factories;

use App\Enums\MediaVisibility;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $filename = fake()->uuid().'.jpg';

        return [
            'visibility' => MediaVisibility::Public,
            'media_collection_id' => null,
            'uploaded_by' => null,
            'disk' => 'media_local',
            'path' => 'sitio/general/'.$filename,
            'filename' => $filename,
            'original_filename' => 'foto-'.fake()->word().'.jpg',
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'width' => 1600,
            'height' => 1067,
            'status' => 'ready',
        ];
    }

    /** Archivo privado (disco media_private). */
    public function private(): static
    {
        return $this->state(function () {
            $filename = fake()->uuid().'.jpg';

            return [
                'visibility' => MediaVisibility::Private,
                'disk' => 'media_private',
                'path' => 'privado/general/'.$filename,
                'filename' => $filename,
            ];
        });
    }

    public function origin(string $origin): static
    {
        return $this->state(fn () => ['origin' => $origin]);
    }
}
