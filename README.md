# ML Habitat — plataforma

Plataforma de ML Habitat, empresa mexicana que diseña, fabrica e instala
muebles a la medida (cocinas integrales, clósets, recámaras, centros de
entretenimiento y zapateras). Reúne en una sola aplicación:

- **Sitio público** (`/`): catálogo, inspiración, solicitud de asesoría y reserva de visita técnica pagada.
- **Portal del cliente** (`/portal`): su proyecto, citas, pagos, documentos y conformidad.
- **App de campo** (`/tecnico`): mediciones e instalaciones desde el teléfono.
- **Panel interno** (`/admin`): CRM, visitas y pagos (Openpay), proyectos, diseño, cotizaciones, fabricación, instalación, calidad, garantías, usuarios, permisos y configuración.

La arquitectura, convenciones y reparto de módulos están en
[`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md) y [`docs/EQUIPO.md`](docs/EQUIPO.md);
el sistema visual en [`docs/design/DESIGN.md`](docs/design/DESIGN.md).

## Stack

Laravel 13 · PHP 8.4 · MariaDB 11.4 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind CSS 4 · shadcn-vue (reka-ui) · Wayfinder · Fortify · Pest ·
Larastan · Pint · Intervention Image · Vite (vite-plus, `vp`).

## Requisitos

- PHP **8.4** con extensiones `bcmath`, `intl`, `mbstring`, `pdo_mysql`, `gd` o `imagick`, `fileinfo`, `openssl`, `zip` (y `ssh2`/`sodium` si se usa Synology por SFTP).
- **MariaDB 11.4** con motor **InnoDB** y `utf8mb4` (las transacciones con `SELECT … FOR UPDATE` de la agenda y los pagos lo requieren). En desarrollo se usa el puerto 3307.
- Composer 2 y Node.js 22+ con npm.
- Opcional: FFmpeg/FFprobe para procesar video.

## Instalación (desarrollo)

```bash
cp .env.example .env              # y ajusta DB_*, APP_URL, MAIL_*
composer install
npm install
php artisan key:generate
php artisan migrate --seed        # esquema + datos de configuración (sin datos de negocio inventados)
php artisan wayfinder:generate    # rutas tipadas para el frontend
php artisan mlhabitat:create-admin
composer run dev                  # servidor, cola, logs y Vite
```

Crea la base de datos antes de migrar:

```sql
CREATE DATABASE mlhabit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Primer administrador

No hay registro público. La primera cuenta se crea por consola:

```bash
php artisan mlhabitat:create-admin
# o, en despliegues automatizados:
php artisan mlhabitat:create-admin --name="Nombre Apellido" --email=admin@mlhabitat.mx --generate-password
```

Crea un **superadministrador** con el correo verificado. Desde ahí, el resto
del equipo se invita en **Administración → Usuarios**: la persona recibe el
correo "Te damos la bienvenida a ML Habitat" con un enlace firmado (72 h)
para crear su contraseña. Los clientes se dan de alta desde el CRM/portal y
los técnicos desde operaciones.

## Variables de entorno

Todas están documentadas en [`.env.example`](.env.example). Las más importantes:

| Grupo         | Variables                                                                                  | Notas                                                                                                                                             |
| ------------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Aplicación    | `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE=America/Mexico_City`, `APP_LOCALE=es`     | `APP_DEBUG=false` en producción.                                                                                                                  |
| Base de datos | `DB_CONNECTION=mariadb`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MariaDB 11.4 InnoDB.                                                                                                                              |
| Sesión        | `SESSION_DRIVER=database`, `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`                      | El driver `database` permite ver y cerrar sesiones desde Cuenta → Seguridad. En producción: `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`. |
| Correo        | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS`, …                                         | Plantillas con la marca en `resources/views/vendor/mail`.                                                                                         |
| Colas         | `QUEUE_CONNECTION=database`                                                                | Correos, imágenes, video y webhooks se procesan en cola.                                                                                          |
| Pagos         | `OPENPAY_*`                                                                                | Ver `docs/pagos/OPENPAY-INTEGRACION.md` (llaves, sandbox/producción, webhook, 3D Secure).                                                         |
| Multimedia    | `MEDIA_*`, `FFMPEG_*`, `NAS_SFTP_*`                                                        | Proveedor local o Synology por SFTP (Tailscale).                                                                                                  |
| Seguridad     | `CSP_ENABLED`, `CSP_ENFORCE`, `CSP_REPORT_URI`, `HSTS_*`                                   | Ver "Seguridad" abajo.                                                                                                                            |

Los parámetros de negocio (porcentaje de anticipo, IVA, vigencia de
cotizaciones, asesorías, tiempo de primer contacto, 2FA obligatoria para el
equipo) **no** son variables de entorno: se editan en
**Administración → Configuración** y quedan auditados. Los valores que
afectan dinero se congelan en cada contrato, visita u orden.

## Colas y tareas programadas

```bash
php artisan queue:work --tries=3 --backoff=10    # supervisor/systemd en producción
php artisan schedule:work                         # desarrollo
# producción (cron):
* * * * * cd /ruta/a/mlhabit && php artisan schedule:run >> /dev/null 2>&1
```

Las tareas programadas viven en `routes/console.php` (p. ej. limpieza de
subidas incompletas, expiración de apartados, recordatorios).

## Pruebas y calidad

Las pruebas usan Pest contra **MariaDB** (no SQLite). Cada módulo puede usar
su propia base para correr en paralelo:

```bash
DB_DATABASE=mlhabit_test php artisan test --compact
DB_DATABASE=mlhabit_test_platform php artisan test --compact tests/Feature/Platform tests/Feature/Auth tests/Feature/Settings
vendor/bin/pint --dirty
vendor/bin/phpstan analyse
node_modules/.bin/vue-tsc --noEmit
```

Nunca ejecutes `migrate:fresh` contra la base principal `mlhabit`.

## Seguridad

- **Autenticación** con Fortify: sin registro público, restablecimiento de contraseña (sin revelar si un correo existe), verificación de correo, cambio de contraseña y verificación en dos pasos (TOTP con confirmación y códigos de recuperación). Las cuentas desactivadas no pueden iniciar sesión y se cierran sus sesiones.
- **Redirección por rol** tras iniciar sesión: cliente → `/portal`, técnico → `/tecnico`, equipo → `/admin`.
- **2FA obligatoria para el equipo** (opcional): Administración → Configuración → Seguridad. Apagada por defecto; quien la active debe tener 2FA propia.
- **Permisos** por rol editables en Administración → Roles y permisos (cada cambio se audita y aplica en la siguiente petición).
- **Cabeceras HTTP** (`App\Http\Middleware\SecurityHeaders`): `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy` (cámara solo para el propio sitio), HSTS en producción y CSP compatible con Vite, Bunny Fonts y Openpay. La CSP se envía en modo **Report-Only** hasta que pongas `CSP_ENFORCE=true`.
- **Límites de peticiones**: inicio de sesión y 2FA (5/min), formularios públicos (`throttle:public-forms`), subidas públicas, acciones sensibles y centro de notificaciones (`App\Providers\RateLimitServiceProvider`).
- **Bitácora** (Administración → Actividad) con antes/después; contraseñas, llaves y datos de tarjeta nunca se guardan ni se muestran.

## Despliegue

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build` (genera Wayfinder y los assets de Vite).
3. `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, credenciales reales de correo y Openpay.
4. `php artisan migrate --force` y `php artisan db:seed --force` (solo configuración; idempotente).
5. `php artisan config:cache route:cache view:cache event:cache`
6. Worker de colas (supervisor/systemd) y cron del scheduler.
7. HTTPS obligatorio (TLS 1.2+); el webhook de Openpay exige dominio con certificado público.
8. Mantenimiento: `php artisan down --render="errors::503"` muestra la página de mantenimiento con la marca (`resources/views/errors/503.blade.php`).

Las páginas de error (403, 404, 419, 429, 500 y 503) se muestran con la marca
mediante el componente Inertia `errors/Error`; con `APP_DEBUG=true` el 500
conserva la página de depuración de Laravel.
