<?php

namespace Database\Factories;

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
            'media_collection_id' => null,
            'uploaded_by' => null,
            'disk' => 'media_local',
            'path' => 'mr-insight/general/'.$filename,
            'filename' => $filename,
            'original_filename' => $filename,
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'width' => 1600,
            'height' => 1067,
            'status' => 'ready',
        ];
    }
}
