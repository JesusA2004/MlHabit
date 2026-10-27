<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\StorageProvider;
use App\Services\ActivityLogger;
use App\Services\MediaUploadService;
use App\Services\MediaUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        $sort = $request->query('orden', 'reciente');

        $media = Media::query()
            ->with(['collection', 'variants'])
            ->when($request->query('tipo'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('coleccion'), fn ($q, $path) => $q->whereHas('collection', fn ($c) => $c->where('path', $path)))
            ->when($request->query('q'), fn ($q, $term) => $q->where('original_filename', 'like', '%'.(is_string($term) ? $term : '').'%'))
            ->when($sort === 'tamano', fn ($q) => $q->orderByDesc('size'))
            ->when($sort === 'nombre', fn ($q) => $q->orderBy('original_filename'))
            ->when($sort === 'reciente' || ! in_array($sort, ['tamano', 'nombre'], true), fn ($q) => $q->orderByDesc('created_at'))
            ->paginate(24)
            ->withQueryString();

        $provider = StorageProvider::query()->where('is_active', true)->first();

        return Inertia::render('admin/multimedia/Index', [
            'media' => [
                'data' => $media->getCollection()->map(fn (Media $m) => $this->toArray($m)),
                'currentPage' => $media->currentPage(),
                'lastPage' => $media->lastPage(),
                'total' => $media->total(),
            ],
            'filters' => $request->only(['tipo', 'coleccion', 'q', 'orden']),
            'collections' => MediaCollection::query()->orderBy('path')->get(['id', 'parent_id', 'name', 'path']),
            'storageBytes' => (int) Media::query()->sum('size'),
            'activeProvider' => $provider !== null ? $provider->label : 'Almacenamiento local',
        ]);
    }

    public function store(Request $request, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $request->validate([
            'file' => [
                'required', 'file',
                'max:'.(config('media.max_upload_mb') * 1024),
                'mimes:jpg,jpeg,png,webp,avif,gif,mp4,mov,webm,pdf,vtt,txt',
            ],
            'collection' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $uploader->upload($request->file('file'), $request->input('collection'));

        $activity->log('multimedia.subido', $media, ['filename' => $media->original_filename]);

        return response()->json(['media' => $this->toArray($media)], 201);
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $media = Media::query()
            ->with(['collection', 'variants'])
            ->when($request->query('q'), fn ($q, $term) => $q->where('original_filename', 'like', '%'.(is_string($term) ? $term : '').'%'))
            ->when($request->query('tipo'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return response()->json(['data' => $media->map(fn (Media $m) => $this->toArray($m))]);
    }

    public function update(Request $request, Media $media, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('update', $media);

        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($validated);
        $activity->log('multimedia.actualizado', $media);

        return response()->json(['media' => $this->toArray($media)]);
    }

    public function usage(Media $media, MediaUsageService $usage): JsonResponse
    {
        Gate::authorize('delete', $media);

        return response()->json(['usages' => $usage->usages($media)]);
    }

    public function destroy(Request $request, Media $media, MediaUsageService $usage, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('delete', $media);

        $usages = $usage->usages($media);
        $force = $request->boolean('force');

        if ($usages !== [] && ! $force) {
            return response()->json(['usages' => $usages], 409);
        }

        $name = $media->original_filename;

        if ($usages !== []) {
            $usage->clearReferences($media);
        }

        $media->delete();

        $activity->log('multimedia.eliminado', $media, ['filename' => $name, 'forzado' => $force && $usages !== []]);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Media $media): array
    {
        return [
            'id' => $media->id,
            'filename' => $media->original_filename,
            'type' => $media->type->value,
            'status' => $media->status->value,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'durationSeconds' => $media->duration_seconds,
            'url' => $media->url(),
            'posterUrl' => $media->variantUrl('poster'),
            'altText' => $media->alt_text,
            'title' => $media->title,
            'destinationPath' => $media->collection?->path.'/'.$media->filename,
            'disk' => $media->disk,
            'createdAt' => $media->created_at?->toIso8601String(),
        ];
    }
}
