<?php

namespace App\Services\Storage;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Throwable;

/**
 * Envoltorio del disco local montado sobre el NAS.
 *
 * - Antes de CADA operación comprueba que el NAS sigue montado (y
 *   alcanzable): si se desmontó, el punto de montaje es un directorio
 *   vacío del servidor y un disco local normal escribiría ahí sin avisar.
 * - Las escrituras son atómicas: se escribe a un temporal oculto hermano
 *   (`.<nombre>.<aleatorio>.part`) y luego se renombra al destino. Si algo
 *   falla, el temporal se borra y nunca queda un archivo a medias.
 */
final class MountGuardAdapter implements FilesystemAdapter
{
    public function __construct(
        private readonly FilesystemAdapter $inner,
        private readonly SynologyMount $mount,
    ) {}

    public function fileExists(string $path): bool
    {
        $this->mount->assertAvailable();

        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        $this->mount->assertAvailable();

        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->mount->assertAvailable();
        $this->atomically($path, $config, fn (string $temporary) => $this->inner->write($temporary, $contents, $config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->mount->assertAvailable();
        $this->atomically($path, $config, fn (string $temporary) => $this->inner->writeStream($temporary, $contents, $config));
    }

    public function read(string $path): string
    {
        $this->mount->assertAvailable();

        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        $this->mount->assertAvailable();

        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->mount->assertAvailable();
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->mount->assertAvailable();
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->mount->assertAvailable();
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->mount->assertAvailable();
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        $this->mount->assertAvailable();

        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $this->mount->assertAvailable();

        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        $this->mount->assertAvailable();

        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        $this->mount->assertAvailable();

        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $this->mount->assertAvailable();

        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->mount->assertAvailable();
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->mount->assertAvailable();
        $this->inner->copy($source, $destination, $config);
    }

    /** Ruta del temporal oculto junto al destino. */
    public static function temporaryPath(string $path): string
    {
        $directory = dirname(str_replace('\\', '/', $path));
        $name = '.'.basename($path).'.'.bin2hex(random_bytes(6)).'.part';

        return ($directory === '.' || $directory === '' ? '' : $directory.'/').$name;
    }

    /**
     * @param  callable(string): void  $write
     */
    private function atomically(string $path, Config $config, callable $write): void
    {
        $temporary = self::temporaryPath($path);

        try {
            $write($temporary);
            $this->inner->move($temporary, $path, $config);
        } catch (Throwable $e) {
            try {
                if ($this->inner->fileExists($temporary)) {
                    $this->inner->delete($temporary);
                }
            } catch (Throwable) {
                // Si el NAS cayó a mitad, el temporal oculto no se sirve nunca.
            }

            throw $e;
        }
    }
}
