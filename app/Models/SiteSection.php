<?php

namespace App\Models;

use App\Cms\Sections\SectionSchema;
use App\Concerns\FlushesPublicSite;
use App\Enums\SiteSectionType;
use Carbon\CarbonImmutable;
use Database\Factories\SiteSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sección de la portada. El contenido JSON siempre se normaliza contra el
 * esquema de su tipo (App\Cms\Sections\*) antes de guardarse.
 *
 * @property int $id
 * @property string $page
 * @property SiteSectionType $type
 * @property int $sort
 * @property bool $is_visible
 * @property array<string, mixed> $content
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SiteSection extends Model
{
    /** @use HasFactory<SiteSectionFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['page', 'type', 'sort', 'is_visible', 'content', 'updated_by'];

    protected $attributes = [
        'page' => 'home',
    ];

    protected static function booted(): void
    {
        static::saving(function (SiteSection $section) {
            $section->content = $section->schema()->normalize($section->content);
        });
    }

    protected function casts(): array
    {
        return [
            'type' => SiteSectionType::class,
            'sort' => 'integer',
            'is_visible' => 'boolean',
            'content' => 'array',
        ];
    }

    public function schema(): SectionSchema
    {
        return $this->type->schema();
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<SiteSection> $query */
    public function scopeForPage(Builder $query, string $page = 'home'): void
    {
        $query->where('page', $page)->orderBy('sort')->orderBy('id');
    }
}
