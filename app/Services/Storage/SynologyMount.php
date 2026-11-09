<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Estado del NAS «SynologyExtra», montado en el servidor por SMB/CIFS
 * (p. ej. `//100.67.89.20/MLHabitat` en `/mnt/mlhabitat-storage`, vía
 * Tailscale; el montaje y sus credenciales los gestiona Linux).
 *
 * El disco `media_synology` es un disco local sobre ese punto de montaje.
 * Si el NAS se desmonta, el directorio sigue existiendo (vacío) en el
 * servidor: por eso, antes de CADA operación, MountGuardAdapter llama a
 * `assertAvailable()`, que comprueba en /proc/self/mountinfo que de verdad
 * hay un montaje CIFS del origen esperado y, opcionalmente, que el NAS
 * responde por TCP (un montaje CIFS sin red puede colgarse).
 *
 * `mountStatus()` solo lee mountinfo (barato, nunca toca el montaje).
 * `check()` es la prueba completa: montaje, red, capacidad y escribir /
 * leer / borrar un archivo temporal.
 */
class SynologyMount
{
    /** Segundos que se recuerda en el proceso la lectura de mountinfo. */
    private const MEMO_SECONDS = 2.0;

    /** Segundos que se recuerda (en caché) si el NAS responde por red. */
    private const REACHABLE_SECONDS = 15;

    /** @var array<string, array{at: float, status: array{mounted: bool, reason: string, source: string|null, fstype: string|null, problem: string|null}}> */
    private static array $memo = [];

    /** Olvida lo memorizado (pruebas o tras cambiar la configuración). */
    public static function flush(): void
    {
        self::$memo = [];
    }

    public function name(): string
    {
        return (string) config('media.synology.name', 'SynologyExtra');
    }

    /** Punto de montaje esperado (raíz del disco media_synology). */
    public function root(): string
    {
        $root = rtrim((string) config('filesystems.disks.'.SynologyDisk::DISK.'.root', '/mnt/mlhabitat-storage'), '/\\');

        return $root === '' ? '/' : $root;
    }

    public function expectedSource(): string
    {
        return (string) config('media.synology.source', '//100.67.89.20/MLHabitat');
    }

    /** Host del origen (`//host/carpeta`). */
    public function host(): string
    {
        $parts = explode('/', ltrim($this->normalizeSource($this->expectedSource(), lower: false), '/'));

        return $parts[0];
    }

    /** Carpeta compartida del origen. */
    public function share(): string
    {
        $parts = explode('/', ltrim($this->normalizeSource($this->expectedSource(), lower: false), '/'), 2);

        return $parts[1] ?? '';
    }

    /**
     * Lee mountinfo y comprueba que la raíz sea un montaje CIFS del origen
     * esperado. No toca el sistema de archivos montado.
     *
     * `problem`: null (todo bien), 'missing' (no hay montaje) o 'mismatch'
     * (hay algo montado, pero no es el NAS esperado).
     *
     * @return array{mounted: bool, reason: string, source: string|null, fstype: string|null, problem: string|null}
     */
    public function mountStatus(): array
    {
        $file = (string) config('media.synology.mountinfo', '/proc/self/mountinfo');

        $lines = is_file($file) ? @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        if ($lines === false) {
            return $this->status(false, 'Este servidor no informa montajes (no es Linux o falta mountinfo).', null, null, 'missing');
        }

        $root = $this->root();
        $found = null;
        foreach ($lines as $line) {
            $entry = $this->parseLine($line);
            // Si hay montajes apilados en el mismo punto, manda el último.
            if ($entry !== null && rtrim($entry['point'], '/') === rtrim($root, '/')) {
                $found = $entry;
            }
        }

        if ($found === null) {
            return $this->status(false, "El NAS no está montado en {$root}.", null, null, 'missing');
        }

        $fstypes = array_map('strtolower', (array) config('media.synology.fstypes', ['cifs', 'smb3']));
        if (! in_array(strtolower($found['fstype']), $fstypes, true)) {
            return $this->status(false, "En {$root} hay un montaje «{$found['fstype']}», no SMB/CIFS.", $found['source'], $found['fstype'], 'mismatch');
        }

        if ($this->normalizeSource($found['source']) !== $this->normalizeSource($this->expectedSource())) {
            return $this->status(false, "En {$root} está montado otro origen ({$found['source']}).", $found['source'], $found['fstype'], 'mismatch');
        }

        return $this->status(true, 'NAS montado correctamente.', $found['source'], $found['fstype'], null);
    }

    /** ¿El NAS responde en su puerto SMB? (memo en caché unos segundos) */
    public function reachable(bool $fresh = false): bool
    {
        if (! (bool) config('media.synology.reachability_check', true)) {
            return true;
        }

        $port = (int) config('media.synology.port', 445);
        $key = 'storage:synology:reachable:'.$this->host().':'.$port;

        if (! $fresh) {
            try {
                $cached = Cache::get($key);
                if (is_bool($cached)) {
                    return $cached;
                }
            } catch (Throwable) {
                // Sin caché: se prueba directo.
            }
        }

        $result = $this->probeTcp($this->host(), $port);

        try {
            Cache::put($key, $result, now()->addSeconds(self::REACHABLE_SECONDS));
        } catch (Throwable) {
            // Sin caché: no pasa nada, la próxima vez se vuelve a probar.
        }

        return $result;
    }

    /**
     * Lo usa el guardián del disco antes de cada operación.
     *
     * @throws SynologyUnavailableException
     */
    public function assertAvailable(): void
    {
        $status = $this->memoizedStatus();
        if (! $status['mounted']) {
            throw new SynologyUnavailableException($status['reason']);
        }

        if (! $this->reachable()) {
            throw new SynologyUnavailableException('El NAS no responde en la red (Tailscale).');
        }
    }

    /**
     * Prueba completa sin caché. Serializable para guardarse en
     * StorageProvider.last_test_result.
     *
     * @return array{status: 'ok'|'error'|'disconnected', state: 'connected'|'disconnected'|'error', message: string, checks: array{mount: bool, reachable: bool, write: bool, read: bool, delete: bool}, capacity: array{totalBytes: int, usedBytes: int, freeBytes: int}|null, checkedAt: string}
     */
    public function check(): array
    {
        $checks = ['mount' => false, 'reachable' => false, 'write' => false, 'read' => false, 'delete' => false];

        $status = $this->mountStatus();
        $this->remember($status);
        if (! $status['mounted']) {
            return $this->result($status['problem'] === 'mismatch' ? 'error' : 'disconnected', $status['reason'], $checks);
        }
        $checks['mount'] = true;

        if (! $this->reachable(fresh: true)) {
            return $this->result('disconnected', 'El NAS no responde en la red (Tailscale).', $checks);
        }
        $checks['reachable'] = true;

        $capacity = $this->capacity();

        $disk = Storage::disk(SynologyDisk::DISK);
        $path = '.storage-check/'.Str::uuid().'.tmp';
        $content = (string) Str::uuid();
        $state = 'connected';
        $message = 'Conexión, escritura, lectura y borrado correctos.';

        try {
            if ($disk->put($path, $content) === false) {
                return $this->result('error', 'No se pudo escribir en el NAS. Revisa los permisos de la carpeta compartida.', $checks, $capacity);
            }
            $checks['write'] = true;

            $read = $disk->get($path);
            if ($read !== $content) {
                $state = 'error';
                $message = 'La lectura en el NAS no coincidió con lo escrito.';
            } else {
                $checks['read'] = true;
            }

            $checks['delete'] = $disk->delete($path) && ! $disk->exists($path);
            if (! $checks['delete'] && $state === 'connected') {
                $state = 'error';
                $message = 'No se pudo borrar el archivo de prueba en el NAS. Revisa los permisos.';
            }
        } catch (SynologyUnavailableException $e) {
            return $this->result('disconnected', $e->getMessage(), $checks, $capacity);
        } catch (Throwable $e) {
            Log::warning('Prueba del NAS fallida.', ['error' => Str::limit($e->getMessage(), 300)]);
            $state = 'error';
            $message = $checks['write']
                ? 'El NAS falló al leer o borrar el archivo de prueba.'
                : 'No se pudo escribir en el NAS. Revisa los permisos de la carpeta compartida.';
        } finally {
            if (! $checks['delete']) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Se intentó; el NAS no respondió.
                }
            }
        }

        return $this->result($state, $message, $checks, $capacity);
    }

    /**
     * @return array{totalBytes: int, usedBytes: int, freeBytes: int}|null
     */
    public function capacity(): ?array
    {
        $root = $this->root();
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);
        if ($total === false || $free === false) {
            return null;
        }

        return [
            'totalBytes' => (int) $total,
            'usedBytes' => max(0, (int) $total - (int) $free),
            'freeBytes' => (int) $free,
        ];
    }

    /**
     * @return array{mounted: bool, reason: string, source: string|null, fstype: string|null, problem: string|null}
     */
    private function memoizedStatus(): array
    {
        $key = $this->memoKey();
        $hit = self::$memo[$key] ?? null;
        if ($hit !== null && (microtime(true) - $hit['at']) < self::MEMO_SECONDS) {
            return $hit['status'];
        }

        $status = $this->mountStatus();
        $this->remember($status);

        return $status;
    }

    /**
     * @param  array{mounted: bool, reason: string, source: string|null, fstype: string|null, problem: string|null}  $status
     */
    private function remember(array $status): void
    {
        self::$memo[$this->memoKey()] = ['at' => microtime(true), 'status' => $status];
    }

    private function memoKey(): string
    {
        return md5(implode('|', [
            (string) config('media.synology.mountinfo'),
            $this->root(),
            $this->expectedSource(),
            implode(',', (array) config('media.synology.fstypes', [])),
        ]));
    }

    private function probeTcp(string $host, int $port): bool
    {
        if ($host === '') {
            return false;
        }

        $timeout = max(0.1, (float) config('media.synology.connect_timeout', 2));
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (! is_resource($socket)) {
            return false;
        }
        fclose($socket);

        return true;
    }

    /**
     * Formato: id padre mayor:menor raíz PUNTO opciones [opcionales…] - TIPO ORIGEN superopciones
     *
     * @return array{point: string, fstype: string, source: string}|null
     */
    private function parseLine(string $line): ?array
    {
        $fields = explode(' ', trim($line));
        $separator = array_search('-', $fields, true);
        if ($separator === false || count($fields) < 5 || ! isset($fields[$separator + 2])) {
            return null;
        }

        return [
            'point' => $this->unescape($fields[4]),
            'fstype' => $this->unescape($fields[$separator + 1]),
            'source' => $this->unescape($fields[$separator + 2]),
        ];
    }

    /** mountinfo escapa espacio, tabulador, salto y barra invertida en octal (\040…). */
    private function unescape(string $value): string
    {
        return (string) preg_replace_callback('/\\\\([0-7]{3})/', fn (array $m) => chr((int) octdec($m[1])), $value);
    }

    private function normalizeSource(string $source, bool $lower = true): string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($source)), '/');

        return $lower ? strtolower($normalized) : $normalized;
    }

    /**
     * @return array{mounted: bool, reason: string, source: string|null, fstype: string|null, problem: string|null}
     */
    private function status(bool $mounted, string $reason, ?string $source, ?string $fstype, ?string $problem): array
    {
        return compact('mounted', 'reason', 'source', 'fstype', 'problem');
    }

    /**
     * @param  'connected'|'disconnected'|'error'  $state
     * @param  array{mount: bool, reachable: bool, write: bool, read: bool, delete: bool}  $checks
     * @param  array{totalBytes: int, usedBytes: int, freeBytes: int}|null  $capacity
     * @return array{status: 'ok'|'error'|'disconnected', state: 'connected'|'disconnected'|'error', message: string, checks: array{mount: bool, reachable: bool, write: bool, read: bool, delete: bool}, capacity: array{totalBytes: int, usedBytes: int, freeBytes: int}|null, checkedAt: string}
     */
    private function result(string $state, string $message, array $checks, ?array $capacity = null): array
    {
        return [
            'status' => match ($state) {
                'connected' => 'ok',
                'disconnected' => 'disconnected',
                default => 'error',
            },
            'state' => $state,
            'message' => $message,
            'checks' => $checks,
            'capacity' => $capacity,
            'checkedAt' => now()->toIso8601String(),
        ];
    }
}
