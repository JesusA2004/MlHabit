<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use Carbon\CarbonImmutable;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Testimonio de un cliente. Regla dura: sin consentimiento registrado
 * (`consent_obtained_at`) no puede publicarse. Se aplica aquí, en el
 * modelo, para que ningún camino (panel, seeder, consola) la salte.
 *
 * @property int $id
 * @property string $author_name
 * @property string|null $author_context
 * @property string $quote
 * @property int|null $showcase_project_id
 * @property int|null $media_id
 * @property CarbonImmutable|null $consent_obtained_at
 * @property string|null $consent_note
 * @property bool $is_published
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Media|null $media
 * @property-read ShowcaseProject|null $showcaseProject
 */
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = [
        'author_name', 'author_context', 'quote', 'showcase_project_id', 'media_id',
        'consent_obtained_at', 'consent_note', 'is_published', 'sort',
    ];

    protected static function booted(): void
    {
        static::saving(function (Testimonial $testimonial) {
            if ($testimonial->is_published && $testimonial->consent_obtained_at === null) {
                throw ValidationException::withMessages([
                    'is_published' => 'No se puede publicar un testimonio sin registrar el consentimiento del cliente.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'consent_obtained_at' => 'datetime',
            'is_published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<ShowcaseProject, $this> */
    public function showcaseProject(): BelongsTo
    {
        return $this->belongsTo(ShowcaseProject::class);
    }

    /**
     * Publicado y con consentimiento (doble verificación al leer).
     *
     * @param  Builder<Testimonial>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('consent_obtained_at');
    }
}
