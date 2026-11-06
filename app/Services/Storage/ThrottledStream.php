<?php

namespace App\Services\Storage;

use HashContext;

/**
 * Envoltura de lectura para copiar archivos entre discos:
 *
 * - limita la velocidad (KB/s del proveedor) durmiendo entre bloques, de
 *   modo que la subida real al NAS nunca supere el límite configurado;
 * - calcula la huella (xxh128) mientras el archivo pasa, sin leerlo dos
 *   veces del origen;
 * - informa los bytes leídos para el progreso.
 *
 * Funciona con cualquier consumidor que lea con fread/stream_copy_to_stream
 * (disco local) o fread + fstat (SFTP de phpseclib).
 */
final class ThrottledStream
{
    public const PROTOCOL = 'mlh-transfer';

    /** @var array<string, array{inner: resource, bytesPerSecond: int, hash: HashContext|null, onRead: (callable(int): void)|null}> */
    private static array $pending = [];

    /** @var resource|null */
    public $context;

    /** @var resource */
    private $inner;

    private int $bytesPerSecond = 0;

    private ?HashContext $hash = null;

    /** @var (callable(int): void)|null */
    private $onRead = null;

    private int $position = 0;

    private float $startedAt = 0.0;

    /**
     * @param  resource  $inner
     * @param  (callable(int): void)|null  $onRead
     * @return resource
     */
    public static function wrap($inner, int $limitKbps = 0, ?HashContext $hash = null, ?callable $onRead = null)
    {
        if (! in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }

        $id = bin2hex(random_bytes(8));
        self::$pending[$id] = [
            'inner' => $inner,
            'bytesPerSecond' => max(0, $limitKbps) * 1024,
            'hash' => $hash,
            'onRead' => $onRead,
        ];

        $stream = fopen(self::PROTOCOL.'://'.$id, 'rb');
        if ($stream === false) {
            unset(self::$pending[$id]);

            return $inner;
        }

        return $stream;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $id = substr($path, strlen(self::PROTOCOL) + 3);
        if (! isset(self::$pending[$id])) {
            return false;
        }

        $state = self::$pending[$id];
        unset(self::$pending[$id]);

        $this->inner = $state['inner'];
        $this->bytesPerSecond = $state['bytesPerSecond'];
        $this->hash = $state['hash'];
        $this->onRead = $state['onRead'];
        $this->startedAt = microtime(true);

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($count < 1) {
            return '';
        }

        $chunk = fread($this->inner, $count);
        if ($chunk === false) {
            return false;
        }

        $length = strlen($chunk);
        if ($length === 0) {
            return '';
        }

        $this->position += $length;
        if ($this->hash !== null) {
            hash_update($this->hash, $chunk);
        }
        if ($this->onRead !== null) {
            ($this->onRead)($length);
        }

        if ($this->bytesPerSecond > 0) {
            $expected = $this->position / $this->bytesPerSecond;
            $elapsed = microtime(true) - $this->startedAt;
            if ($expected > $elapsed) {
                usleep((int) (($expected - $elapsed) * 1_000_000));
            }
        }

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_eof(): bool
    {
        return feof($this->inner);
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        // Solo se permite "rebobinar" antes de empezar (lo hace Flysystem).
        return $offset === 0 && $whence === SEEK_SET && $this->position === 0;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->inner);
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_close(): void
    {
        if (is_resource($this->inner)) {
            fclose($this->inner);
        }
    }
}
