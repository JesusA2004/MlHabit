<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Página compuesta en MR. INSIGHT Studio.
 *
 * - `kind = system`: páginas del sitio con ruta fija (inicio, mi-historia,
 *   mi-proceso, redes). No se pueden eliminar ni cambiar de slug.
 * - `kind = custom`: páginas creadas desde Studio, servidas en /{slug}.
 *
 * @property int $id
 * @property string $key
 * @property string $kind
 * @property string $title
 * @property string|null $slug
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property array<string, mixed>|null $draft
 * @property int $draft_revision
 * @property int|null $draft_updated_by
 * @property Carbon|null $draft_updated_at
 * @property int|null $published_version_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StudioPage extends Model
{
    public const SYSTEM_PATHS = [
        'home' => '/',
        'mi-historia' => '/mi-historia',
        'mi-proceso' => '/mi-proceso',
        'redes' => '/redes',
        'tip' => '/tips',
    ];

    /** Slugs que una página personalizada nunca puede ocupar. */
    public const RESERVED_SLUGS = [
        'admin', 'tips', 'media', 'mi-historia', 'mi-proceso', 'redes', 'aviso-de-privacidad',
        'login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor-challenge',
        'user', 'settings', 'dashboard', 'up', 'vista-previa', 'storage', 'build', 'api', 'email',
        'confirm-password', 'verify-email',
    ];

    protected $fillable = [
        'key', 'kind', 'title', 'slug', 'seo_title', 'seo_description', 'draft', 'draft_revision',
        'draft_updated_by', 'draft_updated_at', 'published_version_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'draft_revision' => 'integer',
            'draft_updated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return HasMany<StudioVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(StudioVersion::class)->orderByDesc('number');
    }

    /** @return BelongsTo<StudioVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(StudioVersion::class, 'published_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function draftEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'draft_updated_by');
    }

    public function isSystem(): bool
    {
        return $this->kind === 'system';
    }

    public function publicPath(): string
    {
        return self::SYSTEM_PATHS[$this->key] ?? '/'.$this->slug;
    }

    /**
     * El borrador difiere de lo publicado (comparación estructural).
     */
    public function hasUnpublishedChanges(): bool
    {
        $published = $this->publishedVersion?->document;

        return $published === null || json_encode($published) !== json_encode($this->draft);
    }
}
