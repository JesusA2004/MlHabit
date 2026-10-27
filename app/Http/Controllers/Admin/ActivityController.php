<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Activity\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * /admin/actividad: bitácora de auditoría con filtros por persona, módulo
 * (prefijo de la acción), tipo de entidad, texto y rango de fechas.
 * Paginación simple (sin COUNT total) porque la tabla crece sin límite.
 */
class ActivityController extends Controller
{
    public function index(Request $request, ActivityPresenter $presenter): Response
    {
        $filters = [
            'usuario' => (string) $request->query('usuario', ''),
            'modulo' => (string) $request->query('modulo', ''),
            'entidad' => (string) $request->query('entidad', ''),
            'desde' => self::date($request->query('desde')),
            'hasta' => self::date($request->query('hasta')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $logs = ActivityLog::query()
            ->with('user:id,name,role')
            ->when($filters['usuario'] === 'sistema', fn (Builder $q) => $q->whereNull('user_id'))
            ->when(ctype_digit($filters['usuario']), fn (Builder $q) => $q->where('user_id', (int) $filters['usuario']))
            ->when($filters['modulo'] !== '', fn (Builder $q) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $filters['modulo']).'.%'))
            ->when($filters['entidad'] !== '', fn (Builder $q) => $q->where('subject_type', $filters['entidad']))
            ->when($filters['desde'], fn (Builder $q, string $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['hasta'], fn (Builder $q, string $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
                $q->where(fn (Builder $w) => $w->where('action', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->simplePaginate(30)
            ->withQueryString();

        return Inertia::render('admin/actividad/Index', [
            'logs' => [
                'data' => $presenter->presentMany($logs->getCollection()),
                'currentPage' => $logs->currentPage(),
                'hasMore' => $logs->hasMorePages(),
                'prevUrl' => $logs->previousPageUrl(),
                'nextUrl' => $logs->nextPageUrl(),
            ],
            'filters' => $filters,
            'options' => [
                'users' => $this->users(),
                'modules' => $this->modules(),
                'entities' => $this->entities(),
            ],
        ]);
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function users(): array
    {
        $users = DB::table('activity_logs')
            ->join('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => ['value' => (string) $u->id, 'label' => (string) $u->name])
            ->all();

        return [['value' => 'sistema', 'label' => 'Sistema (procesos automáticos)'], ...$users];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function modules(): array
    {
        return array_values(DB::table('activity_logs')
            ->selectRaw("DISTINCT SUBSTRING_INDEX(action, '.', 1) as module")
            ->pluck('module')
            ->map(fn ($m) => ['value' => (string) $m, 'label' => ActivityPresenter::moduleLabel((string) $m)])
            ->sortBy('label')
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function entities(): array
    {
        return array_values(DB::table('activity_logs')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($type) => ['value' => (string) $type, 'label' => (string) ActivityPresenter::subjectLabel((string) $type)])
            ->sortBy('label')
            ->all());
    }
}
