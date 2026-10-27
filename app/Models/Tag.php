<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Resuelve etiquetas por nombre, creando las que falten.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function idsFor(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim(mb_substr($name, 0, 80));
            $slug = Str::slug($name);
            if ($name === '' || $slug === '') {
                continue;
            }
            $ids[] = static::query()->firstOrCreate(['slug' => mb_substr($slug, 0, 100)], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
