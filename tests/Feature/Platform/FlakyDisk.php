<?php

namespace Tests\Feature\Platform;

use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\UnableToConnectToSftpHost;

/**
 * Disco de pruebas que simula el NAS por SFTP: guarda en una carpeta
 * temporal y, con `$down = true`, falla como cuando el host no responde
 * (la misma excepción que lanza el adaptador SFTP real).
 */
final class FlakyDisk implements FlysystemAdapter
{
    public bool $down = false;

    /** Operaciones intentadas (para comprobar el cortacircuitos). */
    public int $calls = 0;

    /** Si se define, se corrompe lo escrito (para probar la verificación). */
    public bool $corruptWrites = false;

    private function __construct(private readonly LocalFilesystemAdapter $inner) {}

    public static function install(string $disk = 'media_synology'): self
    {
        $root = storage_path('framework/testing/disks/'.$disk);
        (new Files)->deleteDirectory($root);
        (new Files)->ensureDirectoryExists($root);

        $flaky = new self(new LocalFilesystemAdapter($root));
        Storage::set($disk, new FilesystemAdapter(new Filesystem($flaky), $flaky, ['throw' => false]));

        return $flaky;
    }

    private function guard(): void
    {
        $this->calls++;
        if ($this->down) {
            throw UnableToConnectToSftpHost::atHostname('nas.tailnet.ts.net');
        }
    }

    public function fileExists(string $path): bool
    {
        $this->guard();

        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        $this->guard();

        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->guard();
        $this->inner->write($path, $this->corruptWrites ? strrev($contents) : $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->guard();
        if ($this->corruptWrites) {
            $this->inner->write($path, strrev((string) stream_get_contents($contents)), $config);

            return;
        }
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        $this->guard();

        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        $this->guard();

        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->guard();
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->guard();
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->guard();
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->guard();
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        $this->guard();

        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $this->guard();

        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        $this->guard();

        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        $this->guard();

        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $this->guard();

        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->guard();
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->guard();
        $this->inner->copy($source, $destination, $config);
    }
}
