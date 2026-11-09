<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StorageProviderKey;
use App\Http\Controllers\Controller;
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
use App\Services\Storage\SynologyMount;
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
 * (local y el NAS «SynologyExtra» montado por SMB/CIFS) y permite probar
 * la conexión al NAS. No hay credenciales en la interfaz: el montaje lo
 * gestiona Linux. La configuración histórica guardada en BD no se expone.
 *
 * También muestra dónde vive cada archivo, los pendientes de subir al NAS
 * (respaldo local) y permite trasladar la biblioteca servidor ⇄ Synology
 * con progreso en vivo (ver StorageMigrationService).
 */
class StorageController extends Controller
{
    public function __construct(private readonly SynologyMount $mount) {}

    public function index(StorageMigrationService $migrations, MediaStorage $mediaStorage, PendingMediaSync $sync): Response
    {
        Gate::authorize('settings.manage');

        $providers = StorageProvider::query()->orderBy('key')->get();
        $latest = StorageMigration::query()->latest('id')->first();

        return Inertia::render('admin/almacenamiento/Index', [
            'providers' => $providers->map(fn (StorageProvider $provider) => $this->present($provider, $mediaStorage))->values(),
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

    public function activate(StorageProvider $provider, ActivityLogger $activity, MediaStorage $mediaStorage): RedirectResponse
    {
        Gate::authorize('settings.manage');

        // Synology solo se activa con una comprobación FRESCA correcta.
        if ($provider->key === StorageProviderKey::Synology) {
            $result = $this->checkSynology($provider, $mediaStorage);
            if ($result['status'] !== 'ok') {
                return back()->with('error', 'No se activó el NAS: '.$result['message']);
            }
        }

        StorageProvider::query()->update(['is_active' => false]);
        $provider->update(['is_active' => true]);

        $activity->log('almacenamiento.proveedor_activado', $provider, ['provider' => $provider->key->value]);

        return back()->with('success', "«{$provider->label}» es ahora el proveedor activo.");
    }

    public function test(StorageProvider $provider, ActivityLogger $activity, MediaStorage $mediaStorage, PendingMediaSync $sync): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($provider->key === StorageProviderKey::Local) {
            $result = $this->testLocal();
            $provider->update(['last_tested_at' => now(), 'last_test_result' => $result]);
        } else {
            $result = $this->checkSynology($provider, $mediaStorage);

            // «En cuanto conecte»: si el NAS respondió, lo que quedó en el
            // servidor por el respaldo se sube ya, sin esperar al programador.
            if ($result['status'] === 'ok' && $sync->pendingCount() > 0) {
                SyncPendingMedia::dispatch();
            }
        }

        $activity->log('almacenamiento.probado', $provider, [
            'provider' => $provider->key->value,
            'result' => $result['status'],
        ]);

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['message'],
        );
    }

    /** Inicia un traslado: «to_synology» (servidor → NAS) o «to_local» (emergencia). */
    public function startMigration(Request $request, StorageMigrationService $migrations, MediaStorage $mediaStorage): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate([
            'direction' => ['required', 'string', 'in:to_synology,to_local'],
            'keep_source' => ['required', 'boolean'],
        ]);

        $toSynology = $data['direction'] === 'to_synology';

        if ($toSynology) {
            $synology = StorageProvider::query()->where('key', StorageProviderKey::Synology->value)->first();
            if ($synology === null) {
                return back()->with('error', 'No existe el proveedor Synology.');
            }
            if ($migrations->active() === null) {
                $result = $this->checkSynology($synology, $mediaStorage);
                if ($result['status'] !== 'ok') {
                    return back()->with('error', 'No se puede mover al NAS: '.$result['message']);
                }
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
     * Comprobación fresca del NAS montado: guarda el resultado y actualiza
     * el cortacircuitos (ok → reanuda subidas directas; si no, las desvía
     * al servidor).
     *
     * @return array{status: 'ok'|'error'|'disconnected', state: 'connected'|'disconnected'|'error', message: string, checks: array{mount: bool, reachable: bool, write: bool, read: bool, delete: bool}, capacity: array{totalBytes: int, usedBytes: int, freeBytes: int}|null, checkedAt: string}
     */
    private function checkSynology(StorageProvider $provider, MediaStorage $mediaStorage): array
    {
        SynologyMount::flush();

        $result = $this->mount->check();

        $provider->update([
            'last_tested_at' => now(),
            'last_test_result' => $result,
        ]);

        if ($result['status'] === 'ok') {
            $mediaStorage->markUp(MediaStorage::REMOTE);
        } else {
            $mediaStorage->markDown(MediaStorage::REMOTE, $result['message']);
        }

        return $result;
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
    private function present(StorageProvider $provider, MediaStorage $mediaStorage): array
    {
        $isLocal = $provider->key === StorageProviderKey::Local;
        $disk = $isLocal ? MediaStorage::LOCAL : MediaStorage::REMOTE;

        $data = [
            'id' => $provider->id,
            'key' => $provider->key->value,
            'label' => $provider->label,
            'isActive' => $provider->is_active,
            'lastTestedAt' => $provider->last_tested_at?->toIso8601String(),
            'lastTestResult' => $provider->last_test_result,
            'filesCount' => Media::query()->where('disk', $disk)->count(),
            'filesBytes' => (int) Media::query()->where('disk', $disk)->sum('size'),
            'space' => $isLocal ? $this->localSpace() : null,
        ];

        if ($provider->key === StorageProviderKey::Synology) {
            $data['synology'] = $this->synologyState($provider, $mediaStorage);
        }

        return $data;
    }

    /**
     * Estado del NAS para la página. NO toca el montaje (un statfs sobre un
     * CIFS colgado bloquearía la página): el montaje en vivo sale de
     * mountinfo y la capacidad, de la última prueba guardada.
     *
     * @return array<string, mixed>
     */
    private function synologyState(StorageProvider $provider, MediaStorage $mediaStorage): array
    {
        $live = $this->mount->mountStatus();
        $last = is_array($provider->last_test_result) ? $provider->last_test_result : [];
        $lastOk = ($last['status'] ?? null) === 'ok';
        $down = $mediaStorage->downReason(MediaStorage::REMOTE);

        if (! $live['mounted']) {
            $state = $live['problem'] === 'mismatch' ? 'error' : 'disconnected';
            $message = $live['reason'];
        } elseif ($down !== null) {
            $state = 'disconnected';
            $message = 'El NAS dejó de responder: '.Str::limit($down, 160);
        } elseif ($lastOk) {
            $state = 'connected';
            $message = (string) ($last['message'] ?? 'NAS conectado.');
        } elseif ($last === [] || ! in_array($last['state'] ?? null, ['error', 'disconnected'], true)) {
            $state = 'disconnected';
            $message = 'NAS montado. Pulsa «Probar conexión» para verificarlo.';
        } else {
            $state = (string) $last['state'];
            $message = (string) ($last['message'] ?? 'La última prueba falló.');
        }

        $capacity = $last['capacity'] ?? null;
        $checks = $last['checks'] ?? null;

        return [
            'name' => $this->mount->name(),
            'host' => $this->mount->host(),
            'protocol' => 'SMB/CIFS',
            'share' => $this->mount->share(),
            'mountPath' => $this->mount->root(),
            'state' => $state,
            'message' => $message,
            'mounted' => $live['mounted'],
            'capacity' => is_array($capacity) && isset($capacity['totalBytes'], $capacity['usedBytes'], $capacity['freeBytes']) ? [
                'totalBytes' => (int) $capacity['totalBytes'],
                'usedBytes' => (int) $capacity['usedBytes'],
                'freeBytes' => (int) $capacity['freeBytes'],
            ] : null,
            'checkedAt' => $provider->last_tested_at?->toIso8601String() ?? ($last['checkedAt'] ?? null),
            'checks' => is_array($checks) ? [
                'mount' => (bool) ($checks['mount'] ?? false),
                'reachable' => (bool) ($checks['reachable'] ?? false),
                'write' => (bool) ($checks['write'] ?? false),
                'read' => (bool) ($checks['read'] ?? false),
                'delete' => (bool) ($checks['delete'] ?? false),
            ] : null,
            'canActivate' => $live['mounted'] && $lastOk,
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
