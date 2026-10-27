# ML Habitat — Arquitectura y convenciones

Documento de referencia para todo el equipo (y agentes). Fuente de verdad del esquema: `database/migrations/2026_10_27_*`.

## Stack

Laravel 13 · PHP 8.4 · MariaDB 11.4 (puerto local 3307) · Inertia 3 · Vue 3 + TS · Tailwind 4 · shadcn-vue (reka-ui) · Wayfinder · Fortify · Pest · Larastan · Pint · Intervention Image · vite-plus (`vp`).

## Recorrido del negocio

Visitante → solicitud (`/solicitar`, crea Customer + Lead con folio y atribución UTM) → asesor contacta → asesoría remota (Consultation, agenda de asesores) → precalificación opcional (Prequalification, revisión manual, nunca "aprobado") → visita técnica pagada con Openpay (SiteVisit + Payment, agenda de técnicos por zona de cobertura) → medición (Measurement versionada, desde móvil en `/tecnico`) → proyecto (Project nace al pasar a medición/diseño) → diseño (DesignVersion versionada, aprobación del cliente ≠ aprobación técnica) → cotización (Quote versionada con partidas) → aceptación del cliente → contrato (Contract congela monto, % anticipo, crédito de visita) → anticipo (Payment) → fabricación (FabricationOrder, solo con diseño técnico aprobado) → instalación (InstallationOrder con tarifario congelado) → evidencia del instalador → conformidad del cliente → calidad (QualityInspection) → autorización interna → pago a proveedor (SupplierPayout, quien ejecuta no autoriza) → cierre → garantía (Warranty, WarrantyClaim) / incidencias (Incident).

## Áreas de la aplicación

| Área           | Prefijo       | Layout                | Quién                            |
| -------------- | ------------- | --------------------- | -------------------------------- |
| Sitio público  | `/`           | `PublicLayout`        | visitantes                       |
| Portal cliente | `/portal`     | `PortalLayout`        | rol `cliente`                    |
| App de campo   | `/tecnico`    | `TechnicianLayout`    | rol `tecnico`                    |
| Panel interno  | `/admin`      | `AppLayout` (sidebar) | personal (`UserRole::isStaff()`) |
| Cuenta         | `/settings/*` | settings layout       | todos                            |

## Rutas

`routes/web.php` carga **todos** los archivos `routes/modules/*.php`. Cada módulo tiene su archivo y nombres de ruta con prefijo propio (`admin.prospectos.*`, `public.catalogo.*`, `portal.*`, `tecnico.*`). Las rutas de admin usan `middleware(['auth', 'verified', 'staff'])` donde `staff` es `App\Http\Middleware\EnsureStaff`. Portal: `['auth', 'verified', 'role:cliente']`; técnico: `['auth', 'role:tecnico']` (middleware `EnsureRole`). URLs en español.

## Autorización

- Permisos: `App\Enums\Permission` (cada uno es un Gate con el mismo nombre). Matriz por rol editable: `role_permissions` (`RolePermission::map()`, defaults en `App\Support\Permissions\RoleDefaults`). `super_admin` = todo.
- Usar `Gate::authorize('leads.view')` / `$this->authorize()` / Policies. Policies combinan permiso + propiedad (IDOR): un técnico solo ve sus visitas/instalaciones; un cliente solo su proyecto (`Project::belongsToUser`).
- Frontend: `auth.user.permissions: string[]` solo para ocultar UI; nunca como seguridad.

## Convenciones de backend

- **Dinero**: columnas `DECIMAL(14,2)`; en PHP `App\Support\Money` (bcmath, string). Prohibido float. Cálculos de dinero solo en backend; el frontend solo muestra.
- **Folios**: `App\Support\Folio::next('L')` → `MLH-L-2026-00001`. Prefijos en la clase.
- **Estados**: siempre PHP backed enums en `app/Enums` con `label()` y `tone()` (`success|warning|danger|info|neutral|brand`). Nunca strings sueltos.
- **Proyecto**: el estado solo cambia con `App\Services\Projects\ProjectWorkflow::transition()`; eventos de cronología con `record()` (`visibility` `client` o `internal`).
- **Agenda**: `App\Services\Scheduling\SlotFinder` (horarios libres) y `BookingService::reserve()` (bloqueo FOR UPDATE + traslape + `slot_key` UNIQUE). Apartados con `holdUntil` expiran; `confirm()` y `release()` son idempotentes.
- **Cobertura**: `App\Services\CoverageService` es la única verdad para vender visitas.
- **Configuración de negocio**: `App\Support\Settings` (grupos `commercial`, `consultation`, `contact`, `brand`, `seo`, `footer`, `leads`). Los valores que afectan dinero se congelan en la entidad.
- **Archivos**: `Media` con `visibility` public/private. Privados en disco `media_private`, descargables solo por `GET /archivos/{media:uuid}` (`archivos.show`) que autoriza contra la entidad dueña. Vincular con el trait `App\Concerns\HasAttachments` (`media_attachments`, `role`: gallery, cover, photo, sketch, render, document, evidence, receipt…). Subida: `ChunkedUploadService`/`MediaUploadService` con visibilidad.
- **Auditoría**: `App\Services\ActivityLogger::log('modulo.verbo', $model, ['before'=>…, 'after'=>…])`; sanea secretos y tarjetas.
- **Transacciones**: `DB::transaction` en reservar, pagar, confirmar pago, acreditar visita, crear proyecto, aceptar cotización.
- **Idempotencia**: claves `idempotency_key` UNIQUE generadas en el cliente (UUID por intento de formulario) y restricciones UNIQUE en DB.
- Form Requests para toda entrada; Resources/arrays explícitos para props de Inertia (nunca modelos completos con datos sensibles); eager loading; paginación.
- Notificaciones: clases `Illuminate\Notifications\Notification` con canales `mail` + `database`. Sin acoplar a proveedor (WhatsApp/SMS se agregan como canal nuevo).
- Colas: imágenes, video, correos, procesamiento de webhooks.

## Convenciones de frontend

- Componentes de `resources/js/components/ui` (shadcn) y primitives de negocio en `resources/js/components/ds` (PageHeader, EmptyState, StatusBadge, Money, DateText, DataView, FilterBar, ConfirmDialog, FormSheet, Timeline, ProgressSteps, KeyValue, SectionCard, FileDropzone, DatePicker, SearchSelect…). No crear "Generic*".
- Prohibido: `window.confirm`, `alert`, `<select>` nativo donde aplique Select/Combobox, `v-html` sin sanitizar (el backend sanea HTML enriquecido con `App\Support\Html::clean`), calendarios a mano.
- Fechas: `@internationalized/date`, locale `es-MX`, zona `America/Mexico_City`.
- Móvil primero: listas con `DataView` (tabla en md+, tarjetas en móvil), formularios en `FormSheet`.
- Estados vacíos con texto específico y CTA solo si hay permiso.

## Pruebas

- Pest contra **MariaDB** (`phpunit.xml`). Cada desarrollador/agente puede aislar su base con `DB_DATABASE=mlhabit_test_x php artisan test`.
- Datos de prueba solo con factories; nada inventado presentado como real.
