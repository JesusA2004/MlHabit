<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StorageProviderKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorageProviderRequest;
use App\Jobs\Storage\PurgeMigrationSources;
use App\Jobs\Storage\SyncPendingMedia;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\StorageMigration;
use App\Models\StorageProvider;
use App\Services\ActivityLogger;
use App\Services\Storage\MediaStorage;
use App\Services\Storage\PendingMediaSync;
use App\Services\Storage\StorageMigrationService;
use App\Services\Storage\SynologyDisk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Panel /admin/almacenamiento: muestra el estado real de cada proveedor
 * (local y Synology) y permite configurar y probar la conexión al NAS.
 * Nunca envía credenciales (contraseña, llave privada) de vuelta al
 * navegador, aunque estén guardadas: solo indica si existen.
 *
 * También muestra dónde vive cada archivo, los pendientes de subir al NAS
 * (respaldo local) y permite trasladar la biblioteca servidor ⇄ Synology
 * con progreso en vivo (ver StorageMigrationService).
 */
class StorageController extends Controller
{
    public function index(StorageMigrationService $migrations, MediaStorage $mediaStorage, PendingMediaSync $sync): Response
    {
        Gate::authorize('settings.manage');

        $providers = StorageProvider::query()->orderBy('key')->get();
        $latest = StorageMigration::query()->latest('id')->first();

        return Inertia::render('admin/almacenamiento/Index', [
            'providers' => $providers->map(fn (StorageProvider $provider) => $this->present($provider))->values(),
            'storage' => $this->summary($mediaStorage, $sync),
            'migration' => $latest !== null ? $migrations->present($latest) : null,
            'history' => StorageMigration::query()->latest('id')->limit(6)->get()
                ->map(fn (StorageMigration $m) => [
                    'id' => $m->id,
                    'direction' => $m->target_disk === MediaStorage::REMOTE ? 'to_synology' : 'to_local',
                    'status' => $m->status->value,
                    'statusLabel' => $m->status->label(),
                    'totalFiles' => $m->total_files,
                    'totalBytes' => $m->total_bytes,
                    'createdAt' => $m->created_at?->toIso8601String(),
                    'finishedAt' => $m->finished_at?->toIso8601String(),
                ])->values(),
            'plan' => [
                'toSynology' => $migrations->plan(MediaStorage::LOCAL),
                'toLocal' => $migrations->plan(MediaStorage::REMOTE),
            ],
        ]);
    }

    public function update(StorageProviderRequest $request, StorageProvider $provider, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($provider->key === StorageProviderKey::Synology, 404);

        $data = $request->validated();
        $current = $provider->config ?? [];

        $provider->update([
            'config' => [
                'disk' => 'media_synology',
                'host' => $data['host'] ?? null,
                'port' => $data['port'] ?? 22,
                'username' => $data['username'] ?? null,
                'auth_method' => $data['auth_method'],
                'password' => ! empty($data['password'])
                    ? $data['password']
                    : ($current['password'] ?? null),
                'private_key_path' => ! empty($data['private_key_path'])
                    ? $data['private_key_path']
                    : ($current['private_key_path'] ?? null),
                'transfer_limit_kbps' => $data['transfer_limit_kbps'] ?? null,
            ],
        ]);

        SynologyDisk::forget();

        // Nunca registrar contraseñas ni rutas de llaves en la bitácora.
        $activity->log('almacenamiento.configurado', $provider, [
            'provider' => $provider->key->value,
            'host' => $data['host'] ?? null,
            'auth_method' => $data['auth_method'],
        ]);

        return back()->with('success', 'Configuración de Synology guardada.');
    }

    public function activate(StorageProvider $provider, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($provider->key === StorageProviderKey::Synology && ($provider->last_test_result['status'] ?? null) !== 'ok') {
            return back()->with('error', 'Prueba la conexión con el NAS antes de activarlo.');
        }

        StorageProvider::query()->update(['is_active' => false]);
        $provider->update(['is_active' => true]);

        $activity->log('almacenamiento.proveedor_activado', $provider, ['provider' => $provider->key->value]);

        return back()->with('success', "«{$provider->label}» es ahora el proveedor activo.");
    }

    public function test(StorageProvider $provider, ActivityLogger $activity, MediaStorage $mediaStorage, PendingMediaSync $sync): RedirectResponse
    {
        Gate::authorize('settings.manage');

        SynologyDisk::forget();

        $result = $provider->key === StorageProviderKey::Local
            ? $this->testLocal()
            : $this->testSynology($provider);

        $provider->update([
            'last_tested_at' => now(),
            'last_test_result' => $result,
        ]);

        $activity->log('almacenamiento.probado', $provider, [
            'provider' => $provider->key->value,
            'result' => $result['status'],
        ]);

        // «En cuanto conecte»: si el NAS respondió, lo que quedó en el
        // servidor por el respaldo se sube ya, sin esperar al programador.
        if ($provider->key === StorageProviderKey::Synology) {
            if ($result['status'] === 'ok') {
                $mediaStorage->markUp(MediaStorage::REMOTE);
                if ($sync->pendingCount() > 0) {
                    SyncPendingMedia::dispatch();
                }
            } elseif ($result['status'] === 'error') {
                $mediaStorage->markDown(MediaStorage::REMOTE, $result['message']);
            }
        }

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['message'],
        );
    }

    /** Inicia un traslado: «to_synology» (servidor → NAS) o «to_local» (emergencia). */
    public function startMigration(Request $request, StorageMigrationService $migrations): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'direction' => ['required', 'string', 'in:to_synology,to_local'],
            'keep_source' => ['required', 'boolean'],
        ]);

        $toSynology = $data['direction'] === 'to_synology';

        if ($toSynology) {
            $synology = StorageProvider::query()->where('key', StorageProviderKey::Synology->value)->first();
            if (($synology?->last_test_result['status'] ?? null) !== 'ok') {
                return back()->with('error', 'Prueba la conexión con el Synology antes de mover archivos.');
            }
        }

        [$source, $target] = $toSynology
            ? [MediaStorage::LOCAL, MediaStorage::REMOTE]
            : [MediaStorage::REMOTE, MediaStorage::LOCAL];

        if ($migrations->plan($source)['files'] === 0) {
            return back()->with('success', 'No hay archivos por mover: todo está ya en el destino.');
        }

        try {
            $migration = $migrations->start($source, $target, (bool) $data['keep_source'], $request->user());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Traslado iniciado: {$migration->total_files} archivos.");
    }

    public function cancelMigration(StorageMigration $migration, StorageMigrationService $migrations): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $migrations->cancel($migration);

        return back()->with('success', 'Traslado cancelado. Lo que ya se movió se queda en el destino y funciona normal.');
    }

    public function retryMigration(StorageMigration $migration, StorageMigrationService $migrations): RedirectResponse
    {
        Gate::authorize('settings.manage');

        try {
            $migrations->retry($migration);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Se reanudó el traslado con los archivos pendientes y fallidos.');
    }

    public function purgeMigrationSources(StorageMigration $migration): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($migration->status->isActive()) {
            return back()->with('error', 'Espera a que termine el traslado.');
        }

        PurgeMigrationSources::dispatch($migration->id);

        return back()->with('success', 'Se están borrando del origen las copias ya verificadas.');
    }

    public function syncPending(PendingMediaSync $sync): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($sync->pendingCount() === 0) {
            return back()->with('success', 'No hay archivos pendientes de subir al Synology.');
        }

        SyncPendingMedia::dispatch();

        return back()->with('success', 'Sincronización en marcha: los archivos pendientes se están subiendo al Synology.');
    }

    /**
     * @return array{status: string, message: string, connect: bool, read: bool, write: bool, checkedAt: string}
     */
    private function testLocal(): array
    {
        $disk = Storage::disk('media_local');
        $probePath = '.storage-check/'.Str::uuid().'.tmp';
        $probeContent = (string) Str::uuid();

        try {
            $disk->put($probePath, $probeContent);
            $read = $disk->get($probePath);
            $disk->delete($probePath);

            $ok = $read === $probeContent;

            return [
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok
                    ? 'Almacenamiento local: conexión, lectura y escritura correctas.'
                    : 'Almacenamiento local: la lectura no coincidió con lo escrito.',
                'connect' => true,
                'read' => $ok,
                'write' => true,
                'checkedAt' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::warning('Prueba de almacenamiento local fallida', ['exception' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'No se pudo escribir en el disco local. Revisa permisos de storage/app/media.',
                'connect' => false,
                'read' => false,
                'write' => false,
                'checkedAt' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * @return array{status: string, message: string, connect: bool, read: bool, write: bool, checkedAt: string}
     */
    private function testSynology(StorageProvider $provider): array
    {
        $config = SynologyDisk::mergeProviderConfig((array) config('filesystems.disks.media_synology', []));
        $hasCredentials = ! empty($config['host']) && ! empty($config['username'])
            && (! empty($config['password']) || ! empty($config['privateKey']));

        if (! $hasCredentials) {
            return [
                'status' => 'pending',
                'message' => 'Synology pendiente de configuración.',
                'connect' => false,
                'read' => false,
                'write' => false,
                'checkedAt' => now()->toIso8601String(),
            ];
        }

        $disk = Storage::disk('media_synology');
        $probePath = '.storage-check/'.Str::uuid().'.tmp';
        $probeContent = (string) Str::uuid();

        try {
            $written = $disk->put($probePath, $probeContent);
            $read = $written !== false ? $disk->get($probePath) : null;
            $disk->delete($probePath);

            $ok = $read === $probeContent;

            return [
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok
                    ? 'Synology: conexión, lectura y escritura correctas a través de Tailscale.'
                    : 'Synology: se conectó pero no se pudo escribir o leer en la carpeta. Revisa los permisos de la carpeta compartida.',
                'connect' => true,
                'read' => $ok,
                'write' => $written !== false,
                'checkedAt' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::warning('Prueba de almacenamiento Synology fallida', ['exception' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'No se pudo conectar con el NAS. Verifica que Tailscale esté activo y las credenciales sean correctas.',
                'connect' => false,
                'read' => false,
                'write' => false,
                'checkedAt' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Dónde vive cada archivo: conteo y tamaño por disco (originales +
     * variantes, incluida la papelera) y pendientes de subir al NAS.
     *
     * @return array<string, mixed>
     */
    private function summary(MediaStorage $mediaStorage, PendingMediaSync $sync): array
    {
        $media = Media::withTrashed()->toBase()
            ->selectRaw('disk, count(*) as files, coalesce(sum(size), 0) as bytes')
            ->groupBy('disk')->get()->keyBy('disk');
        $variants = MediaVariant::query()->toBase()
            ->selectRaw('disk, count(*) as files, coalesce(sum(size), 0) as bytes')
            ->groupBy('disk')->get()->keyBy('disk');

        $labels = [
            MediaStorage::LOCAL => 'Servidor',
            MediaStorage::REMOTE => 'Synology',
            'media_private' => 'Servidor · documentos privados',
        ];

        $disks = [];
        foreach ($labels as $disk => $label) {
            $m = $media->get($disk);
            $v = $variants->get($disk);
            $disks[] = [
                'disk' => $disk,
                'label' => $label,
                'files' => (int) ($m->files ?? 0),
                'variants' => (int) ($v->files ?? 0),
                'bytes' => (int) ($m->bytes ?? 0) + (int) ($v->bytes ?? 0),
            ];
        }

        $lastError = Media::withTrashed()->whereNotNull('pending_sync_to')->whereNotNull('sync_error')
            ->latest('updated_at')->value('sync_error');

        return [
            'disks' => $disks,
            'pendingSync' => $sync->pendingCount(),
            'pendingSyncBytes' => (int) Media::withTrashed()->whereNotNull('pending_sync_to')->sum('size'),
            'pendingSyncError' => $lastError,
            'remoteDown' => $mediaStorage->downReason(MediaStorage::REMOTE) !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(StorageProvider $provider): array
    {
        $config = $provider->config ?? [];
        $disk = $config['disk'] ?? ($provider->key === StorageProviderKey::Local ? 'media_local' : 'media_synology');

        $space = $provider->key === StorageProviderKey::Local ? $this->localSpace() : null;

        return [
            'id' => $provider->id,
            'key' => $provider->key->value,
            'label' => $provider->label,
            'isActive' => $provider->is_active,
            'lastTestedAt' => $provider->last_tested_at?->toIso8601String(),
            'lastTestResult' => $provider->last_test_result,
            'filesCount' => Media::query()->where('disk', $disk)->count(),
            'filesBytes' => (int) Media::query()->where('disk', $disk)->sum('size'),
            'space' => $space,
            'config' => $provider->key === StorageProviderKey::Synology ? [
                'host' => $config['host'] ?? null,
                'port' => $config['port'] ?? 22,
                'username' => $config['username'] ?? null,
                'authMethod' => $config['auth_method'] ?? 'password',
                'hasPassword' => ! empty($config['password']),
                'hasPrivateKey' => ! empty($config['private_key_path']),
                'transferLimitKbps' => $config['transfer_limit_kbps'] ?? null,
                'rootPath' => config('filesystems.disks.media_synology.root'),
            ] : null,
        ];
    }

    /**
     * @return array{totalBytes: int, freeBytes: int}|null
     */
    private function localSpace(): ?array
    {
        $path = storage_path('app/media');
        if (! is_dir($path)) {
            return null;
        }

        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false) {
            return null;
        }

        return ['totalBytes' => (int) $total, 'freeBytes' => (int) $free];
    }
}
