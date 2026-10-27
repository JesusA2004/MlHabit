<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MediaCollectionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Media::class);

        $collections = MediaCollection::query()->orderBy('path')->get(['id', 'parent_id', 'name', 'path']);

        return response()->json(['data' => $collections]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:media_collections,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $parent = isset($validated['parent_id']) ? MediaCollection::query()->find((int) $validated['parent_id']) : null;
        $slug = Str::slug($validated['name']);
        $path = $parent ? "{$parent->path}/{$slug}" : $slug;

        $collection = MediaCollection::query()->firstOrCreate(
            ['path' => $path],
            ['parent_id' => $parent?->id, 'name' => $validated['name'], 'slug' => $slug]
        );

        return response()->json(['data' => $collection], 201);
    }
}
