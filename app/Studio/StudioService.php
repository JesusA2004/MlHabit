<?php

namespace App\Studio;

use App\Models\StudioPage;
use App\Models\StudioVersion;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Flujo editorial de Studio: borrador → vista previa → publicación →
 * historial → restauración. Toda escritura pasa por aquí para que el
 * validador, el control de concurrencia y la bitácora se apliquen siempre.
 */
class StudioService
{
    public function __construct(
        private readonly StudioDocumentValidator $validator,
        private readonly StudioMigrator $migrator,
        private readonly StudioReferences $references,
        private readonly StudioDefaults $defaults,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Garantiza que las páginas del sistema existan, generando su primer
     * documento a partir del contenido actual del CMS y publicándolo como
     * versión inicial (el sitio se ve igual que antes de Studio).
     */
    public function ensureSystemPage(string $key): StudioPage
    {
        $existing = StudioPage::query()->where('key', $key)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($key) {
            $document = $this->validator->validate($this->defaults->forPage($key))['document'];

            $page = StudioPage::query()->create([
                'key' => $key,
                'kind' => 'system',
                'title' => StudioDefaults::SYSTEM_PAGES[$key] ?? $key,
                'draft' => $document,
                'draft_revision' => 1,
                'draft_updated_at' => now(),
            ]);

            $version = $page->versions()->create([
                'number' => 1,
                'kind' => 'publish',
                'document' => $document,
                'summary' => 'Versión inicial generada desde el contenido del CMS',
                'published_at' => now(),
                'created_at' => now(),
            ]);

            $page->update(['published_version_id' => $version->id, 'published_at' => now()]);

            return $page->refresh();
        });
    }

    public function ensureSystemPages(): void
    {
        foreach (array_keys(StudioDefaults::SYSTEM_PAGES) as $key) {
            $this->ensureSystemPage($key);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publishedDocument(StudioPage $page): ?array
    {
        $document = $page->publishedVersion?->document;

        return $document !== null ? $this->migrator->migrate($document) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function draftDocument(StudioPage $page): array
    {
        return $this->migrator->migrate($page->draft ?? $this->publishedDocument($page) ?? $this->defaults->forPage($page->key));
    }

    public function createCustomPage(string $title, string $slug): StudioPage
    {
        $document = $this->validator->validate($this->defaults->forPage('custom'))['document'];

        $page = StudioPage::query()->create([
            'key' => 'p-'.$slug,
            'kind' => 'custom',
            'title' => $title,
            'slug' => $slug,
            'draft' => $document,
            'draft_revision' => 1,
            'draft_updated_by' => Auth::id(),
            'draft_updated_at' => now(),
        ]);

        $this->activity->log('studio.pagina_creada', $page, ['title' => $title, 'slug' => $slug]);

        return $page;
    }

    /**
     * Guarda el borrador si la revisión coincide (prevención de
     * sobrescritura entre sesiones). Devuelve los errores de validación o
     * `conflict` si otra sesión guardó primero.
     *
     * @return array{status: 'ok'|'invalid'|'conflict', revision: int, errors: array<string, string>, document?: array<string, mixed>}
     */
    public function saveDraft(StudioPage $page, mixed $document, int $baseRevision, bool $force = false): array
    {
        $result = $this->validator->validate($document);
        if ($result['errors'] !== []) {
            return ['status' => 'invalid', 'revision' => $page->draft_revision, 'errors' => $result['errors']];
        }

        return DB::transaction(function () use ($page, $result, $baseRevision, $force) {
            $fresh = StudioPage::query()->lockForUpdate()->findOrFail($page->id);

            if (! $force && $fresh->draft_revision !== $baseRevision) {
                return ['status' => 'conflict', 'revision' => $fresh->draft_revision, 'errors' => []];
            }

            $fresh->update([
                'draft' => $result['document'],
                'draft_revision' => $fresh->draft_revision + 1,
                'draft_updated_by' => Auth::id(),
                'draft_updated_at' => now(),
            ]);

            return ['status' => 'ok', 'revision' => $fresh->draft_revision, 'errors' => [], 'document' => $result['document']];
        });
    }

    /**
     * Valida el borrador completo y sus referencias; si todo está bien,
     * crea una versión inmutable y la marca como publicada.
     *
     * @return array{status: 'ok'|'invalid', errors: list<string>, version?: StudioVersion}
     */
    public function publish(StudioPage $page, ?string $summary): array
    {
        $result = $this->validator->validate($this->draftDocument($page));
        $problems = [...array_values($result['errors']), ...$this->references->problems($result['document'])];

        if ($problems !== []) {
            return ['status' => 'invalid', 'errors' => array_values(array_unique($problems))];
        }

        $version = DB::transaction(function () use ($page, $result, $summary) {
            $version = $page->versions()->create([
                'number' => $this->nextNumber($page),
                'kind' => 'publish',
                'document' => $result['document'],
                'summary' => $summary !== null && $summary !== '' ? mb_substr($summary, 0, 255) : null,
                'created_by' => Auth::id(),
                'published_at' => now(),
                'created_at' => now(),
            ]);

            $page->update([
                'draft' => $result['document'],
                'published_version_id' => $version->id,
                'published_at' => now(),
            ]);

            return $version;
        });

        $this->activity->log('studio.publicada', $page, ['page' => $page->title, 'version' => $version->number, 'summary' => $summary]);

        return ['status' => 'ok', 'errors' => [], 'version' => $version];
    }

    /**
     * Copia una versión al borrador. Antes respalda el borrador actual si
     * tiene cambios sin publicar, así restaurar nunca destruye trabajo.
     */
    public function restore(StudioPage $page, StudioVersion $version): StudioPage
    {
        DB::transaction(function () use ($page, $version) {
            if ($page->hasUnpublishedChanges() && $page->draft !== null) {
                $page->versions()->create([
                    'number' => $this->nextNumber($page),
                    'kind' => 'snapshot',
                    'document' => $page->draft,
                    'summary' => "Respaldo automático antes de restaurar la versión {$version->number}",
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            }

            $page->update([
                'draft' => $this->migrator->migrate($version->document),
                'draft_revision' => $page->draft_revision + 1,
                'draft_updated_by' => Auth::id(),
                'draft_updated_at' => now(),
            ]);
        });

        $this->activity->log('studio.version_restaurada', $page, ['page' => $page->title, 'version' => $version->number]);

        return $page->refresh();
    }

    public function discardDraft(StudioPage $page): StudioPage
    {
        $published = $this->publishedDocument($page);
        if ($published !== null) {
            $page->update([
                'draft' => $published,
                'draft_revision' => $page->draft_revision + 1,
                'draft_updated_by' => Auth::id(),
                'draft_updated_at' => now(),
            ]);
            $this->activity->log('studio.borrador_descartado', $page, ['page' => $page->title]);
        }

        return $page->refresh();
    }

    private function nextNumber(StudioPage $page): int
    {
        return ((int) StudioVersion::query()->where('studio_page_id', $page->id)->max('number')) + 1;
    }
}
