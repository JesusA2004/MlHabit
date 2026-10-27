<?php

namespace App\Http\Controllers\Admin\Web;

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\MediaVisibility;
use App\Enums\VisualOrigin;
use App\Exceptions\RejectedUploadException;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\StorageProvider;
use App\Services\ActivityLogger;
use App\Services\MediaUploadService;
use App\Services\MediaUsageService;
use App\Services\Web\MediaPresenter;
use App\Services\Web\Paginated;
use App\Services\Web\PublicSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biblioteca multimedia (/admin/multimedia): cuadrícula/lista, filtros,
 * metadatos, reemplazo de archivo y borrado seguro (bloqueado si se usa).
 * Los archivos privados solo aparecen para quien tiene `sensitive.view`.
 */
class MediaController extends Controller
{
    private const SORTS = ['reciente', 'antiguo', 'nombre', 'tamano'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        $canPrivate = Gate::allows('viewPrivate', Media::class);
        $filters = $this->filters($request, $canPrivate);
        $perPage = $request->query('vista') === 'lista' ? 50 : 36;

        $media = $this->query($filters)
            ->with(['collection', 'variants'])
            ->paginate($perPage)
            ->withQueryString();

        $provider = StorageProvider::query()->where('is_active', true)->first();

        return Inertia::render('admin/multimedia/Index', [
            'media' => Paginated::of($media, fn (Media $m) => MediaPresenter::full($m)),
            'filters' => [...$filters, 'vista' => $request->query('vista') === 'lista' ? 'lista' : 'cuadricula'],
            'collections' => MediaCollection::query()
                ->when(! $canPrivate, fn ($q) => $q->where('path', 'not like', 'privado%'))
                ->withCount('media')
                ->orderBy('path')->get(['id', 'parent_id', 'name', 'path'])
                ->map(fn (MediaCollection $c) => ['id' => $c->id, 'parentId' => $c->parent_id, 'name' => $c->name, 'path' => $c->path, 'count' => (int) $c->getAttribute('media_count')])
                ->values(),
            'stats' => [
                'storageBytes' => (int) Media::query()->where('visibility', MediaVisibility::Public->value)->sum('size'),
                'count' => Media::query()->where('visibility', MediaVisibility::Public->value)->count(),
                'activeProvider' => $provider !== null ? $provider->label : 'Almacenamiento local',
            ],
            'limits' => self::limits(),
            'originOptions' => VisualOrigin::options(),
            'can' => [
                'upload' => Gate::allows('create', Media::class),
                'viewPrivate' => $canPrivate,
            ],
        ]);
    }

    /** Búsqueda JSON para el selector de medios (solo públicos y listos). */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $filters = $this->filters($request, false);
        $media = $this->query($filters)
            ->where('status', MediaStatus::Ready->value)
            ->with(['variants', 'collection'])
            ->paginate(30)
            ->withQueryString();

        return response()->json(Paginated::of($media, fn (Media $m) => MediaPresenter::full($m)));
    }

    public function store(Request $request, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $request->validate([
            'file' => ['required', 'file', 'max:'.((int) config('media.max_image_mb', 40) * 1024)],
            'collection' => ['nullable', 'string', 'max:255', 'not_regex:/^\s*privado/i'],
        ]);

        try {
            $media = $uploader->upload($request->file('file'), $request->input('collection') ?: 'sitio/general', MediaVisibility::Public);
        } catch (RejectedUploadException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $activity->log('multimedia.subido', $media, ['filename' => $media->original_filename]);

        return response()->json(['media' => MediaPresenter::full($media->fresh() ?? $media)], 201);
    }

    public function update(Request $request, Media $media, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $media);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'origin' => ['nullable', Rule::enum(VisualOrigin::class)],
            'media_collection_id' => ['nullable', 'integer', 'exists:media_collections,id'],
        ]);

        $media->fill($data);
        $activity->logModelChanges('multimedia.actualizado', $media, ['title', 'alt_text', 'description', 'origin', 'media_collection_id']);
        $media->save();
        PublicSite::flush();

        return response()->json(['media' => MediaPresenter::full($media)]);
    }

    public function replace(Request $request, Media $media, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $media);

        $request->validate(['file' => ['required', 'file', 'max:'.((int) config('media.max_upload_mb', 2048) * 1024)]]);

        $before = $media->original_filename;
        try {
            $media = $uploader->replace($media, $request->file('file'));
        } catch (RejectedUploadException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }
        PublicSite::flush();

        $activity->log('multimedia.reemplazado', $media, ['before' => ['filename' => $before], 'after' => ['filename' => $media->original_filename]]);

        return response()->json(['media' => MediaPresenter::full($media)]);
    }

    public function usage(Media $media, MediaUsageService $usage): JsonResponse
    {
        Gate::authorize('update', $media);

        return response()->json(['usages' => $usage->usages($media)]);
    }

    public function destroy(Media $media, MediaUsageService $usage, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('delete', $media);

        $usages = $usage->usages($media);
        if ($usages !== []) {
            return response()->json([
                'message' => 'Este archivo se está usando. Quítalo de esos lugares antes de eliminarlo.',
                'usages' => $usages,
            ], 409);
        }

        $name = $media->original_filename;
        $media->delete();
        $uploader->deleteFiles($media);

        $activity->log('multimedia.eliminado', $media, ['filename' => $name]);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{maxImageMb: int, maxDocumentMb: int, maxUploadMb: int, chunkSizeMb: int, extensions: list<string>, accept: string}
     */
    public static function limits(): array
    {
        $extensions = MediaUploadService::allowedExtensions();

        return [
            'maxImageMb' => (int) config('media.max_image_mb', 40),
            'maxDocumentMb' => (int) config('media.max_document_mb', 25),
            'maxUploadMb' => (int) config('media.max_upload_mb', 2048),
            'chunkSizeMb' => (int) config('media.chunk_size_mb', 10),
            'extensions' => $extensions,
            'accept' => implode(',', array_map(fn ($e) => '.'.$e, $extensions)),
        ];
    }

    /**
     * @return array{q: string, tipo: string|null, coleccion: string|null, visibilidad: string, origen: string|null, orden: string}
     */
    private function filters(Request $request, bool $canPrivate): array
    {
        $type = MediaType::tryFrom((string) $request->query('tipo'));
        $visibility = $canPrivate && $request->query('visibilidad') === 'private' ? 'private' : 'public';
        $origin = in_array($request->query('origen'), ['render', 'real_photo', 'sin'], true) ? (string) $request->query('origen') : null;
        $sort = in_array($request->query('orden'), self::SORTS, true) ? (string) $request->query('orden') : 'reciente';

        return [
            'q' => is_string($request->query('q')) ? trim(mb_substr($request->query('q'), 0, 100)) : '',
            'tipo' => $type?->value,
            'coleccion' => is_string($request->query('coleccion')) && $request->query('coleccion') !== '' ? $request->query('coleccion') : null,
            'visibilidad' => $visibility,
            'origen' => $origin,
            'orden' => $sort,
        ];
    }

    /**
     * @param  array{q: string, tipo: string|null, coleccion: string|null, visibilidad: string, origen: string|null, orden: string}  $filters
     * @return Builder<Media>
     */
    private function query(array $filters): Builder
    {
        $term = $filters['q'];

        return Media::query()
            ->where('visibility', $filters['visibilidad'])
            ->when($filters['tipo'] !== null, fn ($q) => $q->where('type', $filters['tipo']))
            ->when($filters['coleccion'] !== null, fn ($q) => $q->whereHas('collection', fn ($c) => $c->where(fn ($w) => $w->where('path', $filters['coleccion'])->orWhere('path', 'like', $filters['coleccion'].'/%'))))
            ->when($filters['origen'] === 'sin', fn ($q) => $q->whereNull('origin'))
            ->when(in_array($filters['origen'], ['render', 'real_photo'], true), fn ($q) => $q->where('origin', $filters['origen']))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('original_filename', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('alt_text', 'like', "%{$term}%")))
            ->when($filters['orden'] === 'reciente', fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'))
            ->when($filters['orden'] === 'antiguo', fn ($q) => $q->orderBy('created_at')->orderBy('id'))
            ->when($filters['orden'] === 'nombre', fn ($q) => $q->orderBy('original_filename'))
            ->when($filters['orden'] === 'tamano', fn ($q) => $q->orderByDesc('size'));
    }
}
