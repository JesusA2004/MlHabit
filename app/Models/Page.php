<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use App\Support\Html;
use Carbon\CarbonImmutable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Página legal o informativa (aviso de privacidad, términos, política de
 * cancelación). El cuerpo se sanea SIEMPRE con Html::clean al guardarse y
 * `version` aumenta en cada publicación.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property string $body
 * @property int $version
 * @property bool $is_published
 * @property bool $show_in_footer
 * @property CarbonImmutable|null $published_at
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['slug', 'title', 'summary', 'body', 'version', 'is_published', 'show_in_footer', 'published_at', 'updated_by'];

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if ($page->isDirty('body')) {
                $page->body = Html::clean($page->body);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<Page> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function publicUrl(): string
    {
        return route('public.legal', ['page' => $this->slug]);
    }
}
