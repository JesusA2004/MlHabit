<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Operations\OpsPresenter;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/operaciones/proyectos?q= — búsqueda de proyectos para los
 * selectores de operaciones (incidencias). Incluye sus órdenes.
 */
class ProjectLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('incidents.manage') || $user->can('warranties.manage') || $user->can('installations.manage') || $user->can('fabrication.manage'), 403);

        $term = trim((string) $request->query('q', ''));

        $projects = Project::query()
            ->with(['customer', 'installationOrders:id,project_id,folio', 'fabricationOrders:id,project_id,folio'])
            ->where('status', '!=', 'cancelled')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('folio', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%"))))
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $projects->map(fn (Project $p) => [
                ...OpsPresenter::project($p),
                'installationOrders' => $p->installationOrders->map(fn ($o) => ['value' => $o->id, 'label' => $o->folio])->all(),
                'fabricationOrders' => $p->fabricationOrders->map(fn ($o) => ['value' => $o->id, 'label' => $o->folio])->all(),
            ])->all(),
        ]);
    }
}
