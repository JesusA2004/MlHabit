<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\AttachFileRequest;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Services\ActivityLogger;
use App\Services\Projects\ProjectWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Notas de cronología y archivos del proyecto. */
class ProjectTimelineController extends Controller
{
    public function storeNote(Request $request, Project $project, ProjectWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:4000'],
            'visibility' => ['required', Rule::in(['internal', 'client'])],
        ], [], ['title' => 'título', 'body' => 'detalle', 'visibility' => 'visibilidad']);

        $workflow->record($project, $data['visibility'] === 'client' ? 'client_update' : 'note', $data['title'], $data['body'] ?? null, $request->user(), $data['visibility']);

        return back()->with('success', $data['visibility'] === 'client' ? 'Actualización publicada para el cliente.' : 'Nota interna guardada.');
    }

    /**
     * Archivo privado del proyecto. `client_document` = visible en el portal;
     * el resto (document, contract, render, photo) es solo para el equipo.
     */
    public function storeFile(AttachFileRequest $request, Project $project, ProjectWorkflow $workflow, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $project);
        $data = $request->validated();

        $attachment = $project->attachMedia((int) $data['media_id'], $data['role'], $data['caption'] ?? null);
        $logger->log('proyecto.archivo_agregado', $project, ['media_id' => $attachment->media_id, 'role' => $attachment->role]);

        if ($data['role'] === 'client_document') {
            $workflow->record($project, 'document_shared', 'Compartimos un documento contigo', $data['caption'] ?? null, $request->user(), 'client');
        }

        return back()->with('success', 'Archivo agregado al proyecto.');
    }

    public function updateFile(Request $request, Project $project, MediaAttachment $attachment, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $project);
        $this->assertOwned($project, $attachment);
        $data = $request->validate([
            'role' => ['required', Rule::in(AttachFileRequest::PROJECT_ROLES)],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $duplicate = $project->attachments()->where('media_id', $attachment->media_id)->where('role', $data['role'])->whereKeyNot($attachment->id)->exists();
        if ($duplicate) {
            return back()->with('error', 'Ese archivo ya está en el proyecto con ese tipo.');
        }

        $attachment->update($data);
        $logger->log('proyecto.archivo_actualizado', $project, ['attachment_id' => $attachment->id, ...$data]);

        return back()->with('success', $data['role'] === 'client_document' ? 'El cliente ya puede ver este archivo.' : 'Archivo actualizado.');
    }

    public function destroyFile(Request $request, Project $project, MediaAttachment $attachment, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $project);
        $this->assertOwned($project, $attachment);

        $attachment->delete();
        $logger->log('proyecto.archivo_quitado', $project, ['attachment_id' => $attachment->id, 'media_id' => $attachment->media_id]);

        return back()->with('success', 'Archivo quitado del proyecto.');
    }

    private function assertOwned(Project $project, MediaAttachment $attachment): void
    {
        abort_unless($attachment->attachable_type === $project->getMorphClass() && $attachment->attachable_id === $project->id, 404);
    }
}
