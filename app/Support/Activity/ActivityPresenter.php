<?php

namespace App\Support\Activity;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Traduce la bitácora (`modulo.verbo`) a texto legible en español:
 * etiqueta de la acción, entidad con su folio/nombre y un diff antes/después.
 * Nunca envía `changes` crudo: filtra claves sensibles (segunda capa además
 * de ActivityLogger::sanitize) y recorta valores largos.
 */
final class ActivityPresenter
{
    /** Módulos conocidos (prefijo de la acción). */
    public const MODULES = [
        'prospecto' => 'Prospectos',
        'cliente' => 'Clientes',
        'asesoria' => 'Asesorías',
        'precalificacion' => 'Precalificación',
        'visita' => 'Visitas',
        'pago' => 'Pagos',
        'reembolso' => 'Reembolsos',
        'credito_visita' => 'Créditos de visita',
        'openpay' => 'Openpay',
        'proyecto' => 'Proyectos',
        'medicion' => 'Mediciones',
        'diseno' => 'Diseño',
        'cotizacion' => 'Cotizaciones',
        'contrato' => 'Contratos',
        'fabricacion' => 'Fabricación',
        'instalacion' => 'Instalaciones',
        'calidad' => 'Calidad',
        'incidencia' => 'Incidencias',
        'garantia' => 'Garantías',
        'proveedor' => 'Proveedores',
        'tecnico' => 'Técnicos',
        'tarifario' => 'Tarifarios',
        'pago_proveedor' => 'Pagos a proveedores',
        'agenda' => 'Agenda',
        'cobertura' => 'Cobertura',
        'contenido' => 'Contenido',
        'catalogo' => 'Catálogo',
        'multimedia' => 'Multimedia',
        'red_social' => 'Redes sociales',
        'sitio' => 'Sitio web',
        'usuario' => 'Usuarios',
        'permisos' => 'Roles y permisos',
        'configuracion' => 'Configuración',
        'almacenamiento' => 'Almacenamiento',
        'seguridad' => 'Seguridad',
    ];

    /** Sustantivo singular por módulo para construir etiquetas por defecto. */
    private const NOUNS = [
        'prospecto' => 'Prospecto', 'cliente' => 'Cliente', 'asesoria' => 'Asesoría',
        'precalificacion' => 'Precalificación', 'visita' => 'Visita', 'pago' => 'Pago',
        'reembolso' => 'Reembolso', 'credito_visita' => 'Crédito de visita', 'openpay' => 'Openpay',
        'proyecto' => 'Proyecto', 'medicion' => 'Medición', 'diseno' => 'Diseño',
        'cotizacion' => 'Cotización', 'contrato' => 'Contrato', 'fabricacion' => 'Orden de fabricación',
        'instalacion' => 'Instalación', 'calidad' => 'Calidad', 'incidencia' => 'Incidencia',
        'garantia' => 'Garantía', 'proveedor' => 'Proveedor', 'tecnico' => 'Técnico',
        'tarifario' => 'Tarifario', 'pago_proveedor' => 'Pago a proveedor', 'agenda' => 'Agenda',
        'cobertura' => 'Cobertura', 'contenido' => 'Contenido', 'catalogo' => 'Catálogo',
        'multimedia' => 'Archivo', 'red_social' => 'Red social', 'sitio' => 'Sitio',
        'usuario' => 'Usuario', 'permisos' => 'Permisos', 'configuracion' => 'Configuración',
        'almacenamiento' => 'Almacenamiento', 'seguridad' => 'Seguridad',
    ];

    /** Etiquetas exactas cuando la regla general no suena natural. */
    private const ACTIONS = [
        'usuario.invitado' => 'Usuario invitado',
        'usuario.actualizado' => 'Usuario actualizado',
        'usuario.invitacion_reenviada' => 'Invitación reenviada',
        'usuario.dos_pasos_restablecido' => 'Verificación en dos pasos restablecida',
        'usuario.contrasena_actualizada' => 'Contraseña actualizada',
        'usuario.sesiones_cerradas' => 'Otras sesiones cerradas',
        'permisos.actualizados' => 'Permisos de rol actualizados',
        'permisos.restablecidos' => 'Permisos de rol restablecidos',
        'configuracion.actualizada' => 'Configuración actualizada',
        'almacenamiento.configurado' => 'Almacenamiento configurado',
        'almacenamiento.proveedor_activado' => 'Proveedor de almacenamiento activado',
        'almacenamiento.probado' => 'Conexión de almacenamiento probada',
        'multimedia.subido' => 'Archivo subido',
        'multimedia.actualizado' => 'Archivo actualizado',
        'multimedia.eliminado' => 'Archivo eliminado',
    ];

    /** Clase (basename) → nombre de la entidad en español. */
    public const SUBJECTS = [
        'User' => 'Usuario',
        'Customer' => 'Cliente',
        'Address' => 'Domicilio',
        'Lead' => 'Prospecto',
        'LeadActivity' => 'Seguimiento de prospecto',
        'Consultation' => 'Asesoría',
        'Prequalification' => 'Precalificación',
        'VisitPolicy' => 'Política de visitas',
        'SiteVisit' => 'Visita técnica',
        'Payment' => 'Pago',
        'PaymentAttempt' => 'Intento de pago',
        'Refund' => 'Reembolso',
        'VisitCredit' => 'Crédito de visita',
        'WebhookEvent' => 'Evento de Openpay',
        'Project' => 'Proyecto',
        'ProjectEvent' => 'Evento de proyecto',
        'Measurement' => 'Medición',
        'DesignVersion' => 'Versión de diseño',
        'Quote' => 'Cotización',
        'Contract' => 'Contrato',
        'Supplier' => 'Proveedor',
        'SupplierContact' => 'Contacto de proveedor',
        'SupplierEvaluation' => 'Evaluación de proveedor',
        'TechnicianProfile' => 'Perfil de técnico',
        'FabricationOrder' => 'Orden de fabricación',
        'RateCard' => 'Tarifario',
        'InstallationOrder' => 'Orden de instalación',
        'QualityInspection' => 'Inspección de calidad',
        'SupplierPayout' => 'Pago a proveedor',
        'Incident' => 'Incidencia',
        'Warranty' => 'Garantía',
        'WarrantyClaim' => 'Reclamo de garantía',
        'CoverageZone' => 'Zona de cobertura',
        'AvailabilityRule' => 'Horario de disponibilidad',
        'AvailabilityException' => 'Excepción de agenda',
        'CalendarBooking' => 'Reserva de agenda',
        'Media' => 'Archivo',
        'MediaCollection' => 'Carpeta de multimedia',
        'StorageProvider' => 'Proveedor de almacenamiento',
        'SocialLink' => 'Red social',
        'SiteSetting' => 'Configuración',
        'SiteSection' => 'Sección del sitio',
        'Page' => 'Página',
        'ProductCategory' => 'Categoría de producto',
        'Product' => 'Producto',
        'Finish' => 'Acabado',
        'Tag' => 'Etiqueta',
        'ShowcaseProject' => 'Proyecto de inspiración',
        'Faq' => 'Pregunta frecuente',
        'Testimonial' => 'Testimonio',
    ];

    /** Etiquetas de campos frecuentes en before/after. */
    private const FIELDS = [
        'name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono', 'role' => 'Rol',
        'is_active' => 'Activo', 'status' => 'Estado', 'amount' => 'Monto', 'total' => 'Total',
        'title' => 'Título', 'folio' => 'Folio', 'permissions' => 'Permisos', 'notes' => 'Notas',
        'assigned_to' => 'Asignado a', 'advisor_id' => 'Asesor', 'technician_id' => 'Técnico',
        'starts_at' => 'Inicio', 'ends_at' => 'Fin', 'scheduled_start' => 'Inicio programado',
        'deposit_percent' => 'Anticipo (%)', 'tax_rate' => 'Tasa de IVA', 'prices_include_tax' => 'Precios con IVA',
        'quote_validity_days' => 'Vigencia de cotizaciones (días)', 'enabled' => 'Activo',
        'duration_minutes' => 'Duración (min)', 'buffer_minutes' => 'Margen entre citas (min)',
        'price' => 'Precio', 'booking_window_days' => 'Ventana de reserva (días)',
        'min_notice_hours' => 'Anticipación mínima (h)', 'channels' => 'Canales',
        'follow_up_hours' => 'Primer contacto (h)', 'require_2fa_for_staff' => '2FA obligatoria para el equipo',
        'reason' => 'Motivo', 'severity' => 'Severidad', 'result' => 'Resultado', 'group' => 'Grupo',
        'provider' => 'Proveedor', 'host' => 'Servidor', 'auth_method' => 'Método de acceso',
        'added' => 'Agregados', 'removed' => 'Retirados', 'version' => 'Versión',
    ];

    private const SENSITIVE = '/pass|token|secret|key|credential|card|cvv|recovery|device_session|config/i';

    /** @var array<string, list<string>> */
    private array $columnCache = [];

    public static function actionLabel(string $action): string
    {
        if (isset(self::ACTIONS[$action])) {
            return self::ACTIONS[$action];
        }

        [$module, $verb] = array_pad(explode('.', $action, 2), 2, null);
        $noun = self::NOUNS[$module] ?? Str::ucfirst(str_replace('_', ' ', (string) $module));

        if ($verb === null || $verb === '') {
            return $noun;
        }

        return $noun.' '.str_replace('_', ' ', $verb);
    }

    public static function moduleLabel(string $module): string
    {
        return self::MODULES[$module] ?? Str::ucfirst(str_replace('_', ' ', $module));
    }

    public static function subjectLabel(?string $class): ?string
    {
        if ($class === null || $class === '') {
            return null;
        }

        $base = class_basename($class);

        return self::SUBJECTS[$base] ?? Str::headline($base);
    }

    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function presentMany(Collection $logs): array
    {
        $references = $this->references($logs);

        return array_values($logs->map(fn (ActivityLog $log) => $this->present($log, $references))->all());
    }

    /**
     * @param  array<string, array<int, string>>  $references
     * @return array<string, mixed>
     */
    public function present(ActivityLog $log, array $references = []): array
    {
        $changes = is_array($log->changes) ? $log->changes : [];
        $user = $log->relationLoaded('user') ? $log->getRelation('user') : null;
        $user = $user instanceof User ? $user : null;
        $module = explode('.', $log->action)[0];

        return [
            'id' => $log->id,
            'action' => $log->action,
            'actionLabel' => self::actionLabel($log->action),
            'module' => $module,
            'moduleLabel' => self::moduleLabel($module),
            'userName' => $user !== null ? $user->name : 'Sistema',
            'userRole' => $user?->role->label(),
            'subjectLabel' => self::subjectLabel($log->subject_type),
            'subjectId' => $log->subject_id,
            'subjectReference' => $log->subject_type && $log->subject_id
                ? ($references[$log->subject_type][$log->subject_id] ?? null)
                : null,
            'diff' => $this->diff($changes),
            'details' => $this->details($changes),
            'result' => $log->result,
            'ipAddress' => $log->ip_address,
            'createdAt' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    public function diff(array $changes): array
    {
        $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
        $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];

        $rows = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (! is_string($field) || preg_match(self::SENSITIVE, $field) === 1) {
                continue;
            }

            $rows[] = [
                'field' => $field,
                'label' => self::fieldLabel($field),
                'before' => self::stringify($before[$field] ?? null, $field),
                'after' => self::stringify($after[$field] ?? null, $field),
            ];
        }

        return $rows;
    }

    /**
     * Otros datos (fuera de before/after) como pares etiqueta → valor.
     *
     * @param  array<string, mixed>  $changes
     * @return list<array{label: string, value: string}>
     */
    public function details(array $changes): array
    {
        $rows = [];
        foreach ($changes as $key => $value) {
            if (in_array($key, ['before', 'after'], true) || preg_match(self::SENSITIVE, $key) === 1) {
                continue;
            }

            $rows[] = ['label' => self::fieldLabel($key), 'value' => self::stringify($value, $key)];
        }

        return $rows;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELDS[$field] ?? Str::ucfirst(str_replace(['_id', '_'], ['', ' '], $field));
    }

    private static function stringify(mixed $value, string $field = ''): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if ($field === 'role' && is_string($value) && ($role = UserRole::tryFrom($value))) {
            return $role->label();
        }

        if (is_array($value)) {
            if (array_is_list($value) && in_array($field, ['permissions', 'added', 'removed'], true)) {
                $labels = array_map(
                    fn ($p) => is_string($p) ? (Permission::tryFrom($p)?->label() ?? $p) : (string) json_encode($p),
                    $value,
                );

                return $labels === [] ? 'Ninguno' : Str::limit(implode(', ', $labels), 600);
            }

            if (array_is_list($value) && array_reduce($value, fn ($c, $v) => $c && is_scalar($v), true)) {
                return Str::limit(implode(', ', array_map('strval', $value)), 300);
            }

            return Str::limit((string) json_encode(self::scrub($value), JSON_UNESCAPED_UNICODE), 300);
        }

        return Str::limit(is_scalar($value) ? (string) $value : (string) json_encode($value), 300);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function scrub(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match(self::SENSITIVE, $key) === 1) {
                unset($value[$key]);
            } elseif (is_array($item)) {
                $value[$key] = self::scrub($item);
            }
        }

        return $value;
    }

    /**
     * Folio / nombre legible de cada entidad referida, con una consulta por
     * tipo. Si la tabla no existe o no tiene columnas útiles, se omite.
     *
     * @param  Collection<int, ActivityLog>  $logs
     * @return array<string, array<int, string>>
     */
    private function references(Collection $logs): array
    {
        $result = [];

        foreach ($logs->whereNotNull('subject_type')->whereNotNull('subject_id')->groupBy('subject_type') as $class => $items) {
            $table = $this->tableFor((string) $class);
            if ($table === null) {
                continue;
            }

            $columns = array_values(array_intersect(['folio', 'name', 'title', 'email', 'key', 'label'], $this->columns($table)));
            if ($columns === []) {
                continue;
            }

            try {
                $rows = DB::table($table)
                    ->whereIn('id', $items->pluck('subject_id')->unique()->all())
                    ->get(['id', ...$columns]);
            } catch (Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $row = (array) $row;
                foreach ($columns as $column) {
                    if (! empty($row[$column]) && is_scalar($row[$column])) {
                        $result[(string) $class][(int) $row['id']] = Str::limit((string) $row[$column], 80);
                        break;
                    }
                }
            }
        }

        return $result;
    }

    private function tableFor(string $class): ?string
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        try {
            return (new $class)->getTable();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
    }
}
