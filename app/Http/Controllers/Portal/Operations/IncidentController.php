<?php

namespace App\Http\Controllers\Portal\Operations;

use App\Enums\IncidentCategory;
use App\Enums\IncidentOrigin;
use App\Enums\IncidentSeverity;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Operations\Attachments;
use App\Services\Operations\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /portal/incidencias/nueva — el cliente reporta un problema de SU proyecto.
 * El listado de incidencias del portal lo muestra projects leyendo Incident.
 */
class IncidentController extends Controller
{
    /** Categorías que el cliente puede elegir (lenguaje del cliente). */
    private const CLIENT_CATEGORIES = [
        IncidentCategory::Damage,
        IncidentCategory::MissingPart,
        IncidentCategory::InstallationError,
        IncidentCategory::FabricationError,
    ];

    public function create(Request $request): Response
    {
        $projects = $this->clientProjects($request);

        return Inertia::render('portal/incidencias/Nueva', [
            'projects' => $projects->map(fn (Project $p) => ['id' => $p->id, 'folio' => $p->folio, 'name' => $p->name])->values(),
            'categories' => [
                ['value' => IncidentCategory::Damage->value, 'label' => 'Algo llegó o quedó dañado'],
                ['value' => IncidentCategory::MissingPart->value, 'label' => 'Falta una pieza o accesorio'],
                ['value' => IncidentCategory::InstallationError->value, 'label' => 'Algo quedó mal instalado'],
                ['value' => IncidentCategory::FabricationError->value, 'label' => 'Un mueble no coincide con lo acordado'],
            ],
            'preselectedProjectId' => $request->integer('proyecto') ?: null,
            'maxPhotoBytes' => Attachments::EVIDENCE_MAX_BYTES,
        ]);
    }

    public function store(Request $request, IncidentService $service, Attachments $attachments): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(array_map(fn ($c) => $c->value, self::CLIENT_CATEGORIES))],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
            'media_ids' => ['array', 'max:10'],
            'media_ids.*' => ['integer'],
        ], [
            'title.required' => 'Escribe un título breve.',
            'description.required' => 'Cuéntanos qué pasó.',
        ]);

        $project = $this->clientProjects($request)->firstWhere('id', (int) $data['project_id']);
        if ($project === null) {
            // Proyecto ajeno o inexistente: misma respuesta (no revela existencia).
            throw ValidationException::withMessages(['project_id' => 'Elige uno de tus proyectos.']);
        }

        $incident = DB::transaction(function () use ($data, $project, $request, $service, $attachments) {
            $incident = $service->create([
                'project_id' => $project->id,
                'category' => $data['category'],
                'severity' => IncidentSeverity::Medium->value,
                'title' => $data['title'],
                'description' => $data['description'],
                'visible_to_client' => true,
            ], $request->user(), IncidentOrigin::Client);

            $attachments->attach($incident, $data['media_ids'] ?? [], 'evidence', $request->user(), evidenceOnly: true);

            return $incident;
        });

        return redirect('/portal')->with('success', "Recibimos tu reporte ({$incident->folio}). Te contactaremos pronto.");
    }

    /** @return Collection<int, Project> */
    private function clientProjects(Request $request)
    {
        return Project::query()
            ->whereHas('customer', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', '!=', ProjectStatus::Cancelled->value)
            ->latest()
            ->get();
    }
}
