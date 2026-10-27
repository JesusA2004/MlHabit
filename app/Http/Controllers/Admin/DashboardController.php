<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero /admin. Cada bloque aparece solo si el rol tiene el permiso
 * correspondiente; los datos se calculan con consultas agregadas y se
 * cachean 60 s por rol (la caché se invalida sola al cambiar los permisos
 * del rol porque la clave incluye su lista de permisos).
 *
 * Sin `dashboard.view` se muestra solo el saludo (todo el personal llega
 * aquí tras iniciar sesión, así que no se responde 403).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $permissions = $user->permissions();
        $can = fn (string $permission): bool => $user->isSuperAdmin() || in_array($permission, $permissions, true);

        $sections = $can('dashboard.view') ? [
            'commercial' => $can('leads.view'),
            'consultations' => $can('consultations.view'),
            'visits' => $can('visits.view'),
            'projects' => $can('projects.view'),
            'finance' => $can('payments.view'),
            'incidents' => $can('incidents.view'),
            'warranties' => $can('warranties.view'),
            'agenda' => $can('consultations.view') || $can('visits.view') || $can('installations.view') || $can('schedule.view'),
        ] : array_fill_keys(['commercial', 'consultations', 'visits', 'projects', 'finance', 'incidents', 'warranties', 'agenda'], false);

        $key = 'dashboard:'.$user->role->value.':'.md5(implode(',', $permissions)).':'.now()->format('Y-m-d');

        $data = Cache::remember($key, 60, function () use ($sections, $can) {
            $metrics = new DashboardMetrics;

            $commercial = null;
            if ($sections['commercial']) {
                $commercial = $metrics->commercial();
            }
            if ($sections['consultations']) {
                $commercial = [...($commercial ?? []), 'consultationsToday' => $metrics->consultationsToday()];
            }

            return [
                'commercial' => $commercial,
                'visits' => $sections['visits'] ? $metrics->visits() : null,
                'projects' => $sections['projects'] ? $metrics->projects() : null,
                'finance' => $sections['finance'] ? $metrics->finance() : null,
                'operations' => ($sections['incidents'] || $sections['warranties']) ? [
                    'incidents' => $sections['incidents'] ? $metrics->incidents() : null,
                    'activeWarranties' => $sections['warranties'] ? $metrics->activeWarranties() : null,
                ] : null,
                'agenda' => $sections['agenda'] ? $metrics->agendaToday([
                    'consultations' => $can('consultations.view') || $can('schedule.view'),
                    'visits' => $can('visits.view') || $can('schedule.view'),
                    'installations' => $can('installations.view') || $can('schedule.view'),
                ]) : null,
                'attention' => $can('dashboard.view') ? $metrics->attention($can) : [],
                'generatedAt' => now()->toIso8601String(),
            ];
        });

        return Inertia::render('admin/Dashboard', [
            'greeting' => [
                'name' => strtok($user->name, ' ') ?: $user->name,
                'roleLabel' => $user->role->label(),
                'isSuperAdmin' => $user->role === UserRole::SuperAdmin,
            ],
            'sections' => $sections,
            ...$data,
        ]);
    }
}
