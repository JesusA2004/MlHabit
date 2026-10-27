<?php

namespace App\Http\Controllers\Admin\Projects;

use App\Enums\DesignStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\Projects\ProjectPresenter as P;
use App\Http\Requests\Projects\AttachFileRequest;
use App\Models\DesignVersion;
use App\Models\Project;
use App\Services\Projects\DesignService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    /** Bandeja de diseños: por revisar del cliente, por aprobar técnicamente, etc. */
    public function index(Request $request): Response
    {
        Gate::authorize('designs.view');
        $filters = $request->validate([
            'estado' => ['nullable', Rule::enum(DesignStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $designs = DesignVersion::query()
            ->with(['project:id,uuid,folio,name,customer_id', 'project.customer:id,first_name,last_name', 'creator:id,name'])
            ->when($filters['estado'] ?? null, fn (Builder $q, string $s) => $q->where('status', $s), fn (Builder $q) => $q->where('status', '!=', DesignStatus::Superseded->value))
            ->when($filters['q'] ?? null, fn (Builder $q, string $t) => $q->whereHas('project', fn (Builder $p) => $p->where('folio', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%")))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DesignVersion $d) => [
                'id' => $d->id,
                'number' => $d->number,
                'title' => $d->title,
                'status' => $d->status->toArray(),
                'project' => ['uuid' => $d->project->uuid, 'folio' => $d->project->folio, 'name' => $d->project->name, 'customer' => $d->project->customer?->full_name],
                'created_by' => $d->creator?->name,
                'sent_at' => P::date($d->sent_at),
                'updated_at' => P::date($d->updated_at),
            ]);

        return Inertia::render('admin/proyectos/Disenos', [
            'designs' => $designs,
            'filters' => ['estado' => $filters['estado'] ?? null, 'q' => $filters['q'] ?? ''],
            'statuses' => array_map(fn (DesignStatus $s) => $s->toArray(), DesignStatus::cases()),
        ]);
    }

    public function store(Request $request, Project $project, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.manage');
        $version = $service->create($project, $this->validated($request), $request->user());

        return back()->with('success', "Diseño v{$version->number} creado. Sube los renders y envíalo al cliente.");
    }

    public function update(Request $request, DesignVersion $design, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.manage');
        $result = $service->update($design, $this->validated($request), $request->user());

        return back()->with('success', $result->id === $design->id ? 'Diseño actualizado.' : "Se creó la versión {$result->number}: la anterior ya estaba enviada.");
    }

    public function send(Request $request, DesignVersion $design, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.manage');
        $service->send($design, $request->user());

        return back()->with('success', 'Diseño enviado. El cliente ya puede revisarlo en su portal.');
    }

    public function approveTechnical(Request $request, DesignVersion $design, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.approve_technical');
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $service->approveTechnical($design, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Diseño aprobado técnicamente. Ya puede pasar a fabricación.');
    }

    public function storeFile(AttachFileRequest $request, DesignVersion $design, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.manage');
        $data = $request->validated();
        $service->attachFile($design, (int) $data['media_id'], $data['role'], $data['caption'] ?? null, $request->user());

        return back()->with('success', 'Archivo agregado al diseño.');
    }

    public function destroyFile(Request $request, DesignVersion $design, int $attachment, DesignService $service): RedirectResponse
    {
        Gate::authorize('designs.manage');
        abort_unless($design->attachments()->whereKey($attachment)->exists(), 404);
        $service->detachFile($design, $attachment, $request->user());

        return back()->with('success', 'Archivo quitado del diseño.');
    }

    /** @return array{title: string, notes?: string|null, measurement_id?: int|null} */
    private function validated(Request $request): array
    {
        /** @var array{title: string, notes?: string|null, measurement_id?: int|null} $data */
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'measurement_id' => ['nullable', 'integer', Rule::exists('measurements', 'id')],
        ], [], ['title' => 'título', 'notes' => 'notas', 'measurement_id' => 'medición']);

        return $data;
    }
}
