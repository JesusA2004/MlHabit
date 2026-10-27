<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IncompleteUploadException;
use App\Exceptions\InvalidChunkException;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\UploadSession;
use App\Services\ActivityLogger;
use App\Services\ChunkedUploadService;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UploadSessionController extends Controller
{
    public function store(Request $request, ChunkedUploadService $chunks, MediaUploadService $uploader): JsonResponse
    {
        Gate::authorize('create', Media::class);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string', 'max:100'],
            'totalSize' => ['required', 'integer', 'min:1', 'max:'.(config('media.max_upload_mb') * 1024 * 1024)],
            'collection' => ['nullable', 'string', 'max:255'],
            'checksumSha256' => ['nullable', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/i'],
        ]);

        $session = $chunks->start(
            $validated['filename'],
            $validated['mimeType'],
            $validated['totalSize'],
            $request->user()?->id,
            $validated['collection'] ?? null,
            null,
            $uploader,
            $validated['checksumSha256'] ?? null,
        );

        return response()->json([
            'uuid' => $session->uuid,
            'chunkSize' => $session->chunk_size,
        ], 201);
    }

    /**
     * Permite reanudar una subida tras recargar la página: el cliente
     * consulta cuántos fragmentos ya confirmó el servidor antes de seguir
     * enviando desde ahí, en vez de reiniciar desde cero.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        return response()->json([
            'uuid' => $session->uuid,
            'status' => $session->status->value,
            'receivedSize' => $session->received_size,
            'totalSize' => $session->total_size,
            'chunkSize' => $session->chunk_size,
            'nextChunkIndex' => $session->next_chunk_index,
            'progress' => $session->progressPercent(),
        ]);
    }

    public function appendChunk(Request $request, string $uuid, ChunkedUploadService $chunks): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        $validated = $request->validate([
            'chunk' => ['required', 'file'],
            'chunkIndex' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $session = $chunks->appendChunk($session, $request->file('chunk'), (int) $validated['chunkIndex']);
        } catch (InvalidChunkException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'nextChunkIndex' => $session->refresh()->next_chunk_index,
            ], 409);
        }

        return response()->json([
            'receivedSize' => $session->received_size,
            'totalSize' => $session->total_size,
            'nextChunkIndex' => $session->next_chunk_index,
            'progress' => $session->progressPercent(),
        ]);
    }

    public function complete(Request $request, string $uuid, ChunkedUploadService $chunks, MediaUploadService $uploader, ActivityLogger $activity): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        try {
            $media = $chunks->complete($session, $uploader);
        } catch (IncompleteUploadException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $activity->log('multimedia.subido', $media, ['filename' => $media->original_filename, 'chunked' => true]);

        return response()->json([
            'media' => [
                'id' => $media->id,
                'filename' => $media->original_filename,
                'type' => $media->type->value,
                'url' => $media->url(),
                'status' => $media->status->value,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $uuid, ChunkedUploadService $chunks): JsonResponse
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('create', Media::class);
        abort_if($session->user_id !== $request->user()?->id, 403);

        $chunks->abort($session);

        return response()->json(['aborted' => true]);
    }
}
