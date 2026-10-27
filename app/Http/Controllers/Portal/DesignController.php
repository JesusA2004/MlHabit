<?php

namespace App\Http\Controllers\Portal;

use App\Enums\DesignStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalCustomer;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Models\DesignVersion;
use App\Services\Projects\DesignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    use ResolvesPortalCustomer;

    public function index(Request $request): Response
    {
        $projects = $this->projects($this->customer($request));
        $project = $this->currentProject($request, $projects);

        $designs = $project
            ? DesignVersion::query()
                ->where('project_id', $project->id)
                ->where('status', '!=', DesignStatus::Draft->value)
                ->with('attachments.media')
                ->orderByDesc('number')
                ->get()
                ->map(fn (DesignVersion $d) => P::design($d, forClient: true))
                ->values()
            : [];

        return Inertia::render('portal/Disenos', [
            'projects' => $this->projectOptions($projects),
            'project' => $project ? ['uuid' => $project->uuid, 'folio' => $project->folio, 'name' => $project->name] : null,
            'designs' => $designs,
        ]);
    }

    public function decide(Request $request, DesignVersion $design, DesignService $service): RedirectResponse
    {
        abort_unless(Gate::allows('view', $design), 404);
        Gate::authorize('decide', $design);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'changes'])],
            'layout' => ['boolean'],
            'finishes' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:2000', 'required_if:decision,changes'],
        ], ['comment.required_if' => 'Cuéntanos qué te gustaría cambiar.']);

        $approve = $data['decision'] === 'approve';
        $service->clientDecision($design, $request->user(), $approve, (bool) ($data['layout'] ?? false), (bool) ($data['finishes'] ?? false), $data['comment'] ?? null);

        return back()->with('success', $approve
            ? '¡Gracias! Aprobaste tu diseño. Preparamos tu cotización.'
            : 'Recibimos tus comentarios. Te enviaremos una nueva versión.');
    }
}
