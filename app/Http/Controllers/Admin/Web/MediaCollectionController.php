<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Colecciones (carpetas lógicas) de la biblioteca.
 */
class MediaCollectionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $collections = MediaCollection::query()
            ->when(! Gate::allows('viewPrivate', Media::class), fn ($q) => $q->where('path', 'not like', 'privado%'))
            ->orderBy('path')->get(['id', 'parent_id', 'name', 'path']);

        return response()->json(['data' => $collections]);
    }

    public function store(Request $request, ActivityLogger $activity): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:media_collections,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $parent = isset($validated['parent_id']) ? MediaCollection::query()->find((int) $validated['parent_id']) : null;
        $slug = Str::slug($validated['name']);
        abort_if($slug === '', 422, 'Escribe un nombre válido.');
        $path = $parent ? "{$parent->path}/{$slug}" : $slug;
        abort_if(str_starts_with($path, 'privado') && ! Gate::allows('viewPrivate', Media::class), 403);

        $collection = MediaCollection::query()->firstOrCreate(
            ['path' => $path],
            ['parent_id' => $parent?->id, 'name' => $validated['name'], 'slug' => $slug]
        );

        $activity->log('multimedia.coleccion_creada', $collection, ['path' => $path]);

        return response()->json(['data' => $collection], 201);
    }
}
