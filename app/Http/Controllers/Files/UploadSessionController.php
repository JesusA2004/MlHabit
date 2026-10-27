<?php

namespace App\Http\Controllers\Files;

use App\Enums\MediaVisibility;
use App\Exceptions\IncompleteUploadException;
use App\Exceptions\InvalidChunkException;
use App\Exceptions\RejectedUploadException;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ChunkedUploadService;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Subidas reanudables por fragmentos (/subidas), usadas por
 * `useChunkedUpload.ts` en cualquier módulo:
 *  - `public` (biblioteca del sitio): requiere `media.manage`.
 *  - `private` (documentos/fotos de clientes): cualquier usuario activo.
 *    El archivo queda huérfano hasta que un módulo lo vincule con
 *    `attachMedia` validando la Policy de su entidad; los huérfanos se
 *    purgan a las 24 h (`media:prune-orphans`).
 * Cada sesión solo la puede continuar quien la inició.
 */
class UploadSessionController extends Controller
{
    public function store(Request $request, ChunkedUploadService $chunks, MediaUploadService $uploader): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_active, 403);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string', 'max:100'],
            'totalSize' => ['required', 'integer', 'min:1', 'max:'.((int) config('media.max_upload_mb', 2048) * 1024 * 1024)],
            'collection' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::enum(MediaVisibility::class)],
            'checksumSha256' => ['nullable', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/i'],
        ]);

        if (! MediaUploadService::isAllowedFilename($validated['filename'])) {
            return response()->json([
                'message' => 'Tipo de archivo no permitido. Usa JPG, PNG, WebP, AVIF, GIF, PDF, MP4, WebM o MOV.',
                'errors' => ['filename' => ['Tipo de archivo no permitido.']],
            ], 422);
        }

        $visibility = MediaVisibility::tryFrom((string) ($validated['visibility'] ?? '')) ?? MediaVisibility::Public;
        $collection = isset($validated['collection']) && is_string($validated['collection']) ? trim($validated['collection'], '/ ') : null;

        if ($visibility === MediaVisibility::Public) {
            Gate::authorize('create', Media::class);
            abort_if($collection !== null && str_starts_with($collection, 'privado'), 422, 'Los archivos públicos no pueden ir a una colección privada.');
        } else {
            // Los privados siempre viven bajo privado/…
            $collection = 'privado/'.($collection !== null && $collection !== '' ? preg_replace('#^privado/?#', '', $collection) : 'general');
        }

        $session = $chunks->start(
            $validated['filename'],
            $validated['mimeType'],
            (int) $validated['totalSize'],
            $user->id,
            $collection,
            null,
            $uploader,
            $validated['checksumSha256'] ?? null,
            $visibility->value,
        );

        return response()->json(['uuid' => $session->uuid, 'chunkSize' => $session->chunk_size], 201);
    }

    /** Estado para reanudar tras recargar la página. */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $session = $this->ownSession($request, $uuid);

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
        $session = $this->ownSession($request, $uuid);

        $validated = $request->validate([
            'chunk' => ['required', 'file', 'max:'.(((int) config('media.chunk_size_mb', 10) + 1) * 1024)],
            'chunkIndex' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $session = $chunks->appendChunk($session, $request->file('chunk'), (int) $validated['chunkIndex']);
        } catch (InvalidChunkException $e) {
            return response()->json(['message' => $e->getMessage(), 'nextChunkIndex' => $session->refresh()->next_chunk_index], 409);
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
        $session = $this->ownSession($request, $uuid);

        try {
            $media = $chunks->complete($session, $uploader);
        } catch (IncompleteUploadException|RejectedUploadException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        $activity->log('multimedia.subido', $media, ['filename' => $media->original_filename, 'chunked' => true, 'visibility' => $media->visibility->value]);

        return response()->json([
            'media' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'filename' => $media->original_filename,
                'type' => $media->type->value,
                'visibility' => $media->visibility->value,
                'url' => $media->url(),
                'status' => $media->status->value,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $uuid, ChunkedUploadService $chunks): JsonResponse
    {
        $session = $this->ownSession($request, $uuid);
        $chunks->abort($session);

        return response()->json(['aborted' => true]);
    }

    private function ownSession(Request $request, string $uuid): UploadSession
    {
        $session = UploadSession::query()->where('uuid', $uuid)->firstOrFail();
        abort_if($session->user_id !== $request->user()?->id, 403);

        return $session;
    }
}
