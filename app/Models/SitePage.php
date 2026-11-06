<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use Carbon\CarbonImmutable;
use Database\Factories\SitePageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Página propia del sitio armada con el editor visual (servicios, galerías,
 * nosotros, landings…). Su contenido vive en las tablas del editor con la
 * clave `pageKey()` (`pagina-{id}`): borrador, versiones y secciones
 * publicadas, igual que la portada. Se ve en `/{slug}` solo si está
 * publicada.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $og_media_id
 * @property string|null $template
 * @property bool $is_published
 * @property CarbonImmutable|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Media|null $ogMedia
 * @property-read User|null $editor
 */
class SitePage extends Model
{
    /** @use HasFactory<SitePageFactory> */
    use FlushesPublicSite, HasFactory;

    /** Prefijo de la clave de página en las tablas del editor. */
    public const KEY_PREFIX = 'pagina-';

    protected $fillable = ['slug', 'title', 'seo_title', 'seo_description', 'og_media_id', 'template', 'is_published', 'published_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'og_media_id' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Clave de la página en site_sections / site_page_drafts / site_page_versions. */
    public function pageKey(): string
    {
        return self::KEY_PREFIX.$this->id;
    }

    public function path(): string
    {
        return '/'.$this->slug;
    }

    public function publicUrl(): string
    {
        return url($this->path());
    }

    /** @return BelongsTo<Media, $this> */
    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<SitePage> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
