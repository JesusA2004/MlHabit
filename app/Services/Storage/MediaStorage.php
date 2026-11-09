<?php

namespace App\Services\Storage;

use App\Enums\Permission;
use App\Models\Media;
use App\Models\User;
use App\Notifications\Platform\StorageFallbackActivated;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Escritura de archivos de la biblioteca con respaldo en el servidor.
 *
 * Si el disco preferido es el NAS (`media_synology`) y no responde, el
 * archivo se guarda en `media_local` (servidor) y quien llama marca el
 * Media como pendiente de sincronizar (`pending_sync_to`). El comando
 * `media:sync-pending` (cada 10 min) lo empuja al NAS cuando vuelve.
 *
 * Para no hacer esperar cada subida mientras el NAS está caído, tras un
 * fallo se recuerda «NAS sin conexión» unos minutos (cortacircuitos) y las
 * subidas van directo al servidor. Se avisa a los administradores UNA vez
 * por corte (correo + centro de notificaciones). Si tampoco hay espacio en
 * el servidor, la subida se rechaza (FallbackSpaceException).
 */
class MediaStorage
{
    public const LOCAL = 'media_local';

    public const REMOTE = SynologyDisk::DISK;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SynologyMount $mount,
    ) {}

    public function isRemote(string $disk): bool
    {
        return $disk === self::REMOTE;
    }

    /**
     * Guarda `$contents` en el disco preferido; si es el NAS y falla, en el
     * servidor. Devuelve el disco donde quedó realmente.
     *
     * @param  string|resource  $contents
     *
     * @throws RuntimeException si no se pudo guardar en ningún disco.
     */
    public function put(string $preferredDisk, string $path, $contents): string
    {
        if (! $this->isRemote($preferredDisk)) {
            $this->write($preferredDisk, $path, $contents);

            return $preferredDisk;
        }

        $error = $this->downReason($preferredDisk);

        if ($error === null) {
            try {
                $this->write($preferredDisk, $path, $contents);

                return $preferredDisk;
            } catch (Throwable $e) {
                $error = $this->safeError($e);
                $this->markDown($preferredDisk, $error);
            }
        }

        if (is_resource($contents)) {
            @rewind($contents);
        }

        $this->assertFallbackSpace($contents);
        $this->write(self::LOCAL, $path, $contents);
        $this->fellBack($preferredDisk, $path, $error);

        return self::LOCAL;
    }

    /** Marca un Media como pendiente de subir al disco `$target`. */
    public function markPending(Media $media, string $target, ?string $error = null): void
    {
        $media->forceFill([
            'pending_sync_to' => $target,
            'sync_error' => $error !== null ? Str::limit($error, 480) : $media->sync_error,
        ])->save();
    }

    /**
     * Disco donde se puede LEER el archivo ahora mismo: el suyo o, si es el
     * NAS y no responde (o ya no lo tiene), la copia que se haya conservado
     * en el servidor durante un traslado.
     */
    public function readableDisk(string $disk, string $path): ?string
    {
        // NAS marcado como caído: primero la copia del servidor, sin esperar.
        if ($this->isRemote($disk) && $this->downReason($disk) !== null) {
            try {
                if (Storage::disk(self::LOCAL)->exists($path)) {
                    return self::LOCAL;
                }
            } catch (Throwable) {
                // Se intenta el NAS (el guardián falla rápido si no está).
            }
        }

        try {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo leer del almacenamiento; se intenta la copia del servidor.', [
                'disk' => $disk,
                'error' => $this->safeError($e),
            ]);
        }

        if ($disk !== self::LOCAL) {
            try {
                if (Storage::disk(self::LOCAL)->exists($path)) {
                    return self::LOCAL;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public function downReason(string $disk): ?string
    {
        $value = Cache::get($this->downKey($disk));

        return is_string($value) ? $value : null;
    }

    public function markDown(string $disk, string $error): void
    {
        Cache::put($this->downKey($disk), $error, now()->addMinutes(max(1, (int) config('media.storage.down_minutes', 5))));
    }

    /** El NAS volvió: se reanudan las subidas directas y se rearma el aviso. */
    public function markUp(string $disk): void
    {
        Cache::forget($this->downKey($disk));
        Cache::forget($this->notifiedKey($disk));
    }

    /**
     * Prueba real de escritura/lectura/borrado (sin cortacircuitos).
     */
    public function probe(string $disk): ?string
    {
        if ($this->isRemote($disk)) {
            $result = $this->mount->check();

            return $result['status'] === 'ok' ? null : $result['message'];
        }

        $path = '.storage-check/'.Str::uuid().'.tmp';
        $content = (string) Str::uuid();

        try {
            $storage = Storage::disk($disk);
            if ($storage->put($path, $content) === false) {
                return 'No se pudo escribir el archivo de prueba.';
            }
            $read = $storage->get($path);
            $storage->delete($path);

            return $read === $content ? null : 'La lectura no coincidió con lo escrito.';
        } catch (Throwable $e) {
            return $this->safeError($e);
        }
    }

    /** Límite de transferencia del NAS en KB/s (0 = sin límite). */
    public function transferLimitKbps(): int
    {
        return max(0, (int) config('media.synology.transfer_limit_kbps', 0));
    }

    public function safeError(Throwable $e): string
    {
        $message = trim($e->getMessage()) ?: class_basename($e);

        return Str::limit($message, 480);
    }

    /**
     * @param  string|resource  $contents
     */
    private function write(string $disk, string $path, $contents): void
    {
        if (Storage::disk($disk)->put($path, $contents) === false) {
            throw new RuntimeException("No se pudo guardar el archivo en el disco «{$disk}».");
        }
    }

    /**
     * Antes de guardar en el servidor como respaldo: debe quedar libre al
     * menos el tamaño del archivo + la reserva configurada.
     *
     * @param  string|resource  $contents
     *
     * @throws FallbackSpaceException
     */
    private function assertFallbackSpace($contents): void
    {
        $size = null;
        if (is_resource($contents)) {
            $stat = @fstat($contents);
            $size = is_array($stat) && isset($stat['size']) ? (int) $stat['size'] : null;
        } elseif (is_string($contents)) {
            $size = strlen($contents);
        }
        if ($size === null) {
            return;
        }

        try {
            $root = Storage::disk(self::LOCAL)->path('');
            $free = @disk_free_space(is_dir($root) ? $root : storage_path());
        } catch (Throwable) {
            return;
        }
        if ($free === false) {
            return;
        }

        $reserve = max(0, (int) config('media.storage.fallback_reserve_mb', 1024)) * 1024 * 1024;
        if ($free < $size + $reserve) {
            Log::error('El NAS no respondió y el servidor no tiene espacio para el respaldo.', ['size' => $size, 'free' => (int) $free]);

            throw new FallbackSpaceException('El almacenamiento no está disponible en este momento y el servidor no tiene espacio para guardarlo. Inténtalo más tarde.');
        }
    }

    private function fellBack(string $preferredDisk, string $path, string $error): void
    {
        Log::warning('El NAS no respondió: archivo guardado en el servidor y marcado para sincronizar.', [
            'preferred_disk' => $preferredDisk,
            'path' => $path,
            'error' => $error,
        ]);

        if (! Cache::add($this->notifiedKey($preferredDisk), now()->toIso8601String(), now()->addDay())) {
            return;
        }

        try {
            $this->activity->log('almacenamiento.respaldo_local', null, [
                'disk' => $preferredDisk,
                'error' => $error,
            ], 'warning');

            $admins = User::query()->withPermission(Permission::SettingsManage)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new StorageFallbackActivated);
            }
        } catch (Throwable $e) {
            Log::error('No se pudo avisar del respaldo local del almacenamiento.', ['error' => $e->getMessage()]);
        }
    }

    private function downKey(string $disk): string
    {
        return "storage:down:{$disk}";
    }

    private function notifiedKey(string $disk): string
    {
        return "storage:fallback-notified:{$disk}";
    }
}
