<?php

namespace App\Services\Web;

use App\Cms\Layout\LayoutValidator;
use App\Cms\Layout\LegacyLayouts;
use App\Enums\SiteSectionType;
use App\Models\SitePageDraft;
use App\Models\SitePageVersion;
use App\Models\SiteSection;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Flujo borrador → publicación del editor visual.
 *
 * - El borrador (`site_page_drafts`) guarda la página completa y se
 *   autoguarda; nunca se ve en el sitio.
 * - Publicar valida de nuevo, sincroniza `site_sections` (lo que lee el
 *   sitio) y crea una versión inmutable en `site_page_versions`.
 * - Restaurar copia una versión al borrador (hay que publicar después).
 */
class SiteBuilder
{
    /** Páginas editables con el editor visual. */
    public const PAGES = ['home' => 'Portada'];

    /**
     * Orden sugerido de la portada (instalación nueva): portada → por qué
     * nosotros → qué hacemos → cómo funciona → proyectos → visita y
     * cobertura → pagos → testimonios → preguntas → llamada final. El equipo
     * puede reordenar todo en el editor visual.
     *
     * @var list<SiteSectionType>
     */
    public const RECOMMENDED_ORDER = [
        SiteSectionType::Hero,
        SiteSectionType::Benefits,
        SiteSectionType::Categories,
        SiteSectionType::Process,
        SiteSectionType::FeaturedProjects,
        SiteSectionType::HomeVisit,
        SiteSectionType::Coverage,
        SiteSectionType::PaymentOptions,
        SiteSectionType::Testimonials,
        SiteSectionType::Faq,
        SiteSectionType::FinalCta,
    ];

    public function __construct(private readonly ActivityLogger $activity) {}

    /** Borrador de la página; si no existe, nace de lo publicado. */
    public function draft(string $page = 'home'): SitePageDraft
    {
        $draft = SitePageDraft::query()->where('page', $page)->first();
        if ($draft !== null) {
            return $draft;
        }

        return SitePageDraft::query()->firstOrCreate(['page' => $page], [
            'snapshot' => $this->publishedSnapshot($page),
            'revision' => 1,
        ]);
    }

    /**
     * Lo publicado hoy, como snapshot del editor.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function publishedSnapshot(string $page = 'home'): array
    {
        $this->ensureSections($page);

        return [
            'sections' => array_values(SiteSection::query()->forPage($page)->get()
                ->map(fn (SiteSection $s) => $s->toSnapshot())->all()),
        ];
    }

    /** ¿El borrador tiene cambios sin publicar? */
    public function hasUnpublishedChanges(SitePageDraft $draft): bool
    {
        return $this->fingerprint($draft->snapshot) !== $this->fingerprint($this->publishedSnapshot($draft->page));
    }

    /**
     * Autoguardado. `revision` es la versión del borrador sobre la que el
     * editor hizo sus cambios: si no coincide (otra pestaña u otra persona
     * guardó antes), se responde 409 para no pisar su trabajo.
     *
     * @param  array<string, mixed>  $snapshot
     *
     * @throws ValidationException
     */
    public function saveDraft(string $page, array $snapshot, int $revision, User $user): SitePageDraft
    {
        $clean = LayoutValidator::snapshot($snapshot);

        return DB::transaction(function () use ($page, $clean, $revision, $user) {
            $draft = $this->draft($page);
            $draft = SitePageDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if ($draft->revision !== $revision) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Alguien más guardó cambios en esta página. Recarga el editor para ver la versión más reciente.',
                    'revision' => $draft->revision,
                ], 409));
            }

            $draft->forceFill([
                'snapshot' => $clean,
                'revision' => $draft->revision + 1,
                'updated_by' => $user->id,
            ])->save();

            return $draft;
        });
    }

    /**
     * Publica el borrador: valida, sincroniza las secciones del sitio y
     * guarda una versión en el historial.
     *
     * @throws ValidationException
     */
    public function publish(string $page, User $user, ?string $note = null): SitePageVersion
    {
        $version = DB::transaction(function () use ($page, $user, $note) {
            $draft = $this->draft($page);
            $draft = SitePageDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $snapshot = LayoutValidator::snapshot($draft->snapshot);

            if (LayoutValidator::h1Count($snapshot['sections']) > 1) {
                throw ValidationException::withMessages([
                    'sections' => ['Solo puede haber un título principal (H1) por página. Cambia los demás a H2.'],
                ]);
            }

            $existing = SiteSection::query()->where('page', $page)->get()->keyBy('key');
            $keys = [];
            foreach ($snapshot['sections'] as $index => $section) {
                $model = $existing->get($section['key']) ?? new SiteSection(['page' => $page]);
                $model->forceFill([
                    'key' => $section['key'],
                    'page' => $page,
                    'type' => $section['type'],
                    'sort' => ($index + 1) * 10,
                    'is_visible' => $section['isVisible'],
                    'content' => $section['content'],
                    'layout' => $section['layout'],
                    'layout_version' => $section['layout']['version'],
                    'updated_by' => $user->id,
                ])->save();
                $keys[] = $section['key'];
            }

            SiteSection::query()->where('page', $page)
                ->where(fn ($q) => $q->whereNotIn('key', $keys)->orWhereNull('key'))
                ->delete();

            $number = (int) SitePageVersion::query()->where('page', $page)->lockForUpdate()->max('number') + 1;
            $version = SitePageVersion::query()->create([
                'page' => $page,
                'number' => $number,
                'snapshot' => $snapshot,
                'note' => $note !== null && trim($note) !== '' ? Str::limit(trim(strip_tags($note)), 290) : null,
                'published_by' => $user->id,
                'published_at' => now(),
            ]);

            $draft->forceFill(['snapshot' => $snapshot, 'revision' => $draft->revision + 1, 'updated_by' => $user->id])->save();

            return $version;
        });

        PublicSite::flush();
        $this->activity->log('sitio.pagina_publicada', $version, ['page' => $page, 'number' => $version->number, 'note' => $version->note]);

        return $version;
    }

    /** Copia una versión al borrador (no publica). */
    public function restore(SitePageVersion $version, User $user): SitePageDraft
    {
        $draft = DB::transaction(function () use ($version, $user) {
            $draft = $this->draft($version->page);
            $draft = SitePageDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $draft->forceFill([
                'snapshot' => $version->snapshot,
                'revision' => $draft->revision + 1,
                'updated_by' => $user->id,
            ])->save();

            return $draft;
        });

        $this->activity->log('sitio.version_restaurada', $version, ['page' => $version->page, 'number' => $version->number]);

        return $draft;
    }

    /** Descarta el borrador y vuelve a lo publicado. */
    public function discard(string $page, User $user): SitePageDraft
    {
        $draft = DB::transaction(function () use ($page, $user) {
            $draft = $this->draft($page);
            $draft = SitePageDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $draft->forceFill([
                'snapshot' => $this->publishedSnapshot($page),
                'revision' => $draft->revision + 1,
                'updated_by' => $user->id,
            ])->save();

            return $draft;
        });

        $this->activity->log('sitio.borrador_descartado', null, ['page' => $page]);

        return $draft;
    }

    /**
     * Instalación nueva sin sembrar: crea las secciones curadas de la
     * portada. Si el equipo ya publicó alguna versión, se respeta (aunque
     * haya borrado todas las secciones).
     */
    private function ensureSections(string $page): void
    {
        if ($page !== 'home'
            || SiteSection::query()->where('page', $page)->exists()
            || SitePageVersion::query()->where('page', $page)->exists()) {
            return;
        }

        foreach (self::recommendedOrder() as $index => $type) {
            $content = $type->schema()->defaults();
            SiteSection::query()->create([
                'page' => $page,
                'type' => $type,
                'sort' => ($index + 1) * 10,
                'is_visible' => true,
                'content' => $content,
                'layout' => LegacyLayouts::for($type, $content),
            ]);
        }
    }

    /**
     * Tipos curados en el orden sugerido (los que no estén en la lista van
     * al final, por si se agregan tipos nuevos).
     *
     * @return list<SiteSectionType>
     */
    public static function recommendedOrder(): array
    {
        $rest = array_filter(SiteSectionType::curated(), fn (SiteSectionType $t) => ! in_array($t, self::RECOMMENDED_ORDER, true));

        return [...self::RECOMMENDED_ORDER, ...array_values($rest)];
    }

    /** @param array<string, mixed> $snapshot */
    private function fingerprint(array $snapshot): string
    {
        $sections = array_map(fn ($s) => is_array($s) ? [
            'key' => $s['key'] ?? null,
            'type' => $s['type'] ?? null,
            'isVisible' => $s['isVisible'] ?? null,
            'layout' => $s['layout'] ?? null,
        ] : null, is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : []);

        return md5((string) json_encode($sections));
    }
}
