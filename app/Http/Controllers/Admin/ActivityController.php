<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visor de /admin/actividad: traduce la bitácora cruda (ActivityLog) a
 * resúmenes legibles. Nunca se envía el arreglo `changes` sin procesar al
 * frontend — cada acción conocida decide qué campos mostrar y con qué
 * etiqueta, y además se filtra cualquier clave que luzca sensible como
 * segunda capa de protección (ActivityLogger ya evita guardar secretos).
 */
class ActivityController extends Controller
{
    /**
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        'studio.pagina_creada' => 'Página de Studio creada',
        'studio.pagina_eliminada' => 'Página de Studio eliminada',
        'studio.publicada' => 'Página publicada desde Studio',
        'studio.version_restaurada' => 'Versión restaurada en Studio',
        'studio.borrador_descartado' => 'Borrador de Studio descartado',
        'studio.vista_previa_compartida' => 'Vista previa compartida',
        'studio.ajustes_actualizados' => 'Ajustes de página actualizados',
        'publicacion.creada' => 'Publicación creada',
        'publicacion.actualizada' => 'Publicación actualizada',
        'publicacion.eliminada' => 'Publicación eliminada',
        'publicacion.publicada' => 'Publicación publicada',
        'publicacion.publicada_automaticamente' => 'Publicación publicada (programada)',
        'publicacion.archivada' => 'Publicación archivada',
        'publicacion.programada' => 'Publicación programada',
        'publicacion.destacada' => 'Publicación destacada',
        'publicacion.no_destacada' => 'Publicación ya no destacada',
        'categoria.creada' => 'Categoría creada',
        'categoria.actualizada' => 'Categoría actualizada',
        'categoria.eliminada' => 'Categoría eliminada',
        'multimedia.subido' => 'Archivo subido',
        'multimedia.actualizado' => 'Archivo actualizado',
        'multimedia.eliminado' => 'Archivo eliminado',
        'pagina.actualizada' => 'Página actualizada',
        'pagina_entrada.creada' => 'Entrada agregada',
        'pagina_entrada.actualizada' => 'Entrada actualizada',
        'pagina_entrada.eliminada' => 'Entrada eliminada',
        'red_social.creada' => 'Red social agregada',
        'red_social.actualizada' => 'Red social actualizada',
        'red_social.eliminada' => 'Red social eliminada',
        'inicio.actualizado' => 'Inicio actualizado',
        'inicio.destacado_cambiado' => 'Destacado de inicio cambiado',
        'apariencia.borrador_guardado' => 'Borrador de apariencia guardado',
        'apariencia.publicada' => 'Apariencia publicada',
        'apariencia.borrador_descartado' => 'Borrador de apariencia descartado',
        'apariencia.restablecida' => 'Apariencia restablecida',
        'almacenamiento.configurado' => 'Almacenamiento configurado',
        'almacenamiento.proveedor_activado' => 'Proveedor de almacenamiento activado',
        'almacenamiento.probado' => 'Conexión de almacenamiento probada',
    ];

    /**
     * @var array<string, string>
     */
    public const SUBJECT_LABELS = [
        'App\Models\StudioPage' => 'Página de Studio',
        'App\\Models\\Post' => 'Publicación',
        'App\\Models\\Category' => 'Categoría',
        'App\\Models\\Media' => 'Archivo',
        'App\\Models\\Page' => 'Página',
        'App\\Models\\PageEntry' => 'Entrada',
        'App\\Models\\SocialLink' => 'Red social',
        'App\\Models\\StorageProvider' => 'Proveedor de almacenamiento',
    ];

    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|key|credential/i';

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $filters = $request->only(['usuario', 'accion', 'desde', 'hasta', 'q']);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($filters['usuario'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['accion'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['desde'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['hasta'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(function ($query) use ($term) {
                $query->where('action', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
            }))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/actividad/Index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (ActivityLog $log) => $this->present($log))->values(),
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'filters' => $filters,
            'actions' => $this->distinctActions(),
            'users' => $this->distinctUsers(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'userName' => $log->user !== null ? $log->user->name : 'Sistema',
            'actionKey' => $log->action,
            'actionLabel' => self::ACTION_LABELS[$log->action] ?? $log->action,
            'subjectLabel' => $log->subject_type ? (self::SUBJECT_LABELS[$log->subject_type] ?? null) : null,
            'subjectId' => $log->subject_id,
            'summary' => $this->summarize($log->changes ?? []),
            'result' => $log->result,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<int, string>
     */
    private function summarize(array $changes): array
    {
        $lines = [];

        foreach ($changes as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key) === 1) {
                continue;
            }

            $label = str($key)->replace('_', ' ')->ucfirst()->toString();
            $printable = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            $lines[] = "{$label}: {$printable}";
        }

        return $lines;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function distinctActions(): array
    {
        return ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn (string $action) => ['value' => $action, 'label' => self::ACTION_LABELS[$action] ?? $action])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function distinctUsers(): array
    {
        return ActivityLog::query()
            ->with('user:id,name')
            ->whereNotNull('user_id')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->map(fn ($user) => ['value' => $user->id, 'label' => $user->name])
            ->values()
            ->toArray();
    }
}
