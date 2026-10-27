<?php

namespace App\Models;

use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\LegacyLayouts;
use App\Cms\Sections\SectionSchema;
use App\Concerns\FlushesPublicSite;
use App\Enums\SiteSectionType;
use Carbon\CarbonImmutable;
use Database\Factories\SiteSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sección PUBLICADA de una página. El contenido JSON siempre se normaliza
 * contra el esquema de su tipo (App\Cms\Sections\*); el lienzo del editor
 * visual (`layout`) llega validado por App\Cms\Layout\LayoutValidator al
 * publicar. El borrador vive en `site_page_drafts`.
 *
 * @property int $id
 * @property string|null $key
 * @property string $page
 * @property SiteSectionType $type
 * @property int $sort
 * @property bool $is_visible
 * @property array<string, mixed> $content
 * @property array<string, mixed>|null $layout
 * @property int $layout_version
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SiteSection extends Model
{
    /** @use HasFactory<SiteSectionFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['key', 'page', 'type', 'sort', 'is_visible', 'content', 'layout', 'layout_version', 'updated_by'];

    protected $attributes = [
        'page' => 'home',
    ];

    protected static function booted(): void
    {
        static::creating(function (SiteSection $section) {
            $section->key ??= (string) Str::uuid();
        });

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
            'layout' => 'array',
            'layout_version' => 'integer',
        ];
    }

    public function schema(): SectionSchema
    {
        return $this->type->schema();
    }

    /**
     * Lienzo de la sección. Las secciones anteriores al editor visual (sin
     * lienzo guardado) se convierten al vuelo desde su contenido tipado.
     *
     * @return array<string, mixed>
     */
    public function resolvedLayout(): array
    {
        return $this->layout ?? LegacyLayouts::for($this->type, $this->content);
    }

    /**
     * Medios que usa la sección (contenido tipado + lienzo).
     *
     * @return list<int>
     */
    public function mediaIds(): array
    {
        return array_values(array_unique([
            ...$this->schema()->mediaIds($this->content),
            ...LayoutValidator::mediaIds($this->layout),
        ]));
    }

    /**
     * Representación como sección de borrador/versión del editor.
     *
     * @return array{key: string, type: string, isVisible: bool, content: array<string, mixed>, layout: array<string, mixed>}
     */
    public function toSnapshot(): array
    {
        return [
            'key' => (string) $this->key,
            'type' => $this->type->value,
            'isVisible' => $this->is_visible,
            'content' => $this->content,
            'layout' => $this->resolvedLayout(),
        ];
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
