<?php

namespace App\Http\Controllers\Technician;

use App\Enums\MeasurementKind;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Http\Requests\Projects\AttachFileRequest;
use App\Http\Requests\Projects\MeasurementRequest;
use App\Models\Measurement;
use App\Services\ActivityLogger;
use App\Services\Projects\MeasurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Captura de medición desde el móvil (solo el técnico de la visita). */
class MeasurementController extends Controller
{
    public function edit(Measurement $measurement): Response
    {
        Gate::authorize('update', $measurement);
        $measurement->load(['items', 'attachments.media', 'siteVisit.customer:id,first_name', 'siteVisit.address']);
        $visit = $measurement->siteVisit;

        return Inertia::render('tecnico/visitas/Medicion', [
            'measurement' => [
                ...P::measurement($measurement),
                'editable' => $measurement->isEditable(),
            ],
            'visit' => $visit ? [
                'uuid' => $visit->uuid,
                'folio' => $visit->folio,
                'customer_first_name' => $visit->customer->first_name,
                'address' => P::address($visit->address),
            ] : null,
            'kinds' => MeasurementKind::options(),
            'spaceTypes' => ['Cocina', 'Clóset', 'Vestidor', 'Recámara', 'Baño', 'Estudio', 'Lavandería', 'Otro'],
        ]);
    }

    public function update(MeasurementRequest $request, Measurement $measurement, MeasurementService $service): RedirectResponse
    {
        Gate::authorize('update', $measurement);
        $service->saveDraft($measurement, $request->validated());

        return back()->with('success', 'Medición guardada.');
    }

    public function submit(Request $request, Measurement $measurement, MeasurementService $service): RedirectResponse
    {
        Gate::authorize('update', $measurement);
        $service->submit($measurement, $request->user());

        $visit = $measurement->siteVisit;

        return ($visit ? to_route('tecnico.visitas.show', $visit) : back())
            ->with('success', "Medición v{$measurement->version} enviada. Ya no se puede editar; si hay que corregir, crea una corrección.");
    }

    public function revise(Request $request, Measurement $measurement, MeasurementService $service): RedirectResponse
    {
        Gate::authorize('update', $measurement);
        $draft = $service->revise($measurement, $request->user());

        return to_route('tecnico.mediciones.edit', $draft)->with('success', "Corrección v{$draft->version} creada. La versión anterior se conserva.");
    }

    public function storeFile(AttachFileRequest $request, Measurement $measurement, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $measurement);
        abort_unless($measurement->isEditable(), 422, 'Esta medición ya se envió.');
        $data = $request->validated();

        $measurement->attachMedia((int) $data['media_id'], $data['role'], $data['caption'] ?? null);
        $logger->log('medicion.archivo_agregado', $measurement, ['media_id' => (int) $data['media_id'], 'role' => $data['role']]);

        return back()->with('success', $data['role'] === 'sketch' ? 'Croquis agregado.' : 'Foto agregada.');
    }

    public function destroyFile(Measurement $measurement, int $attachment, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $measurement);
        abort_unless($measurement->isEditable(), 422, 'Esta medición ya se envió.');
        abort_unless($measurement->attachments()->whereKey($attachment)->delete() > 0, 404);
        $logger->log('medicion.archivo_quitado', $measurement, ['attachment_id' => $attachment]);

        return back()->with('success', 'Archivo quitado.');
    }
}
