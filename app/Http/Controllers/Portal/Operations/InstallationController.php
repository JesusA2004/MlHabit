<?php

namespace App\Http\Controllers\Portal\Operations;

use App\Enums\ClientConformity;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\InstallationOrder;
use App\Services\Operations\Attachments;
use App\Services\Operations\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /portal/instalaciones/{order} — el cliente revisa la evidencia por módulo
 * y responde "Todo conforme" o "Hay pendientes". Su respuesta no es una
 * renuncia a garantías ni a sus derechos como consumidor.
 */
class InstallationController extends Controller
{
    public function show(Request $request, InstallationOrder $installationOrder): Response
    {
        Gate::authorize('viewAsClient', $installationOrder);

        return Inertia::render('portal/instalaciones/Show', [
            'order' => OpsPresenter::installation($installationOrder, 'client'),
            'canRespond' => $request->user()->can('conform', $installationOrder),
            'maxPhotoBytes' => Attachments::EVIDENCE_MAX_BYTES,
            'rightsNotice' => 'Tu conformidad no limita tus garantías ni tus derechos como consumidor.',
        ]);
    }

    public function conformity(Request $request, InstallationOrder $installationOrder, InstallationService $service): RedirectResponse
    {
        Gate::authorize('conform', $installationOrder);

        $data = $request->validate([
            'conformity' => ['required', Rule::enum(ClientConformity::class)],
            'comment' => ['nullable', 'required_if:conformity,'.ClientConformity::Pending->value, 'string', 'max:2000'],
            'pending_item_ids' => ['array'],
            'pending_item_ids.*' => ['integer', Rule::exists('installation_order_items', 'id')->where('installation_order_id', $installationOrder->id)],
            'media_ids' => ['array', 'max:10'],
            'media_ids.*' => ['integer'],
        ], ['comment.required_if' => 'Cuéntanos qué está pendiente.']);

        $conformity = ClientConformity::from($data['conformity']);
        $service->clientConformity(
            $installationOrder, $request->user(), $conformity, $data['comment'] ?? null,
            array_values(array_map('intval', $data['pending_item_ids'] ?? [])), array_values(array_map('intval', $data['media_ids'] ?? [])),
        );

        return back()->with('success', $conformity === ClientConformity::Conforming
            ? '¡Gracias! Registramos tu conformidad. Nuestro equipo de calidad hará la revisión final.'
            : 'Gracias por avisarnos. Registramos tus pendientes y te contactaremos para atenderlos.');
    }
}
