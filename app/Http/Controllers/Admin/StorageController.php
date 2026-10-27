<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StorageProviderKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorageProviderRequest;
use App\Models\Media;
use App\Models\StorageProvider;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
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
 */
class StorageController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('settings.manage');

        $providers = StorageProvider::query()->orderBy('key')->get();

        return Inertia::render('admin/almacenamiento/Index', [
            'providers' => $providers->map(fn (StorageProvider $provider) => $this->present($provider))->values(),
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

    public function test(StorageProvider $provider, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('settings.manage');

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

        return back()->with(
            $result['status'] === 'ok' ? 'success' : 'error',
            $result['message'],
        );
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
        $config = $provider->config ?? [];
        $hasCredentials = ! empty($config['host']) && ! empty($config['username'])
            && (! empty($config['password']) || ! empty($config['private_key_path']));

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
            $disk->put($probePath, $probeContent);
            $read = $disk->get($probePath);
            $disk->delete($probePath);

            $ok = $read === $probeContent;

            return [
                'status' => $ok ? 'ok' : 'error',
                'message' => $ok
                    ? 'Synology: conexión, lectura y escritura correctas a través de Tailscale.'
                    : 'Synology: la lectura no coincidió con lo escrito.',
                'connect' => true,
                'read' => $ok,
                'write' => true,
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
