<?php

namespace Tests\Feature\Platform;

use App\Services\Storage\MountGuardAdapter;
use App\Services\Storage\SynologyMount;
use App\Services\Storage\SynologyUnavailableException;
use Illuminate\Filesystem\Filesystem as Files;
use Illuminate\Filesystem\LocalFilesystemAdapter as IlluminateLocalAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;

/**
 * NAS simulado para pruebas: una carpeta temporal «montada» (mountinfo de
 * prueba que declara un CIFS del origen esperado en esa carpeta) y el disco
 * real del driver (guardián MountGuardAdapter) por encima de este adaptador.
 *
 * - `$down = true`: falla como si el NAS dejara de responder.
 * - `$failWrites = true`: el NAS rechaza escrituras (permisos).
 * - `$failMidWrite = true`: escribe la mitad y falla (corte a mitad).
 * - `$corruptWrites = true`: corrompe lo escrito (verificación de huella).
 */
final class FlakyDisk implements FlysystemAdapter
{
    public const SOURCE = '//100.67.89.20/MLHabitat';

    public bool $down = false;

    public bool $failWrites = false;

    public bool $failMidWrite = false;

    /** Operaciones intentadas (para comprobar el cortacircuitos). */
    public int $calls = 0;

    public bool $corruptWrites = false;

    public string $root;

    private function __construct(private readonly LocalFilesystemAdapter $inner) {}

    public static function install(string $disk = 'media_synology'): self
    {
        $root = storage_path('framework/testing/disks/'.$disk);
        (new Files)->deleteDirectory($root);
        (new Files)->ensureDirectoryExists($root);

        self::mount($root);

        $flaky = new self(new LocalFilesystemAdapter($root));
        $flaky->root = $root;
        $guard = new MountGuardAdapter($flaky, app(SynologyMount::class));
        Storage::set($disk, new IlluminateLocalAdapter(new Filesystem($guard), $guard, ['root' => $root, 'throw' => false]));

        return $flaky;
    }

    /**
     * Configura el disco media_synology en `$root` y escribe un mountinfo
     * de prueba. `$line` = null → mountinfo sin el montaje del NAS.
     */
    public static function mount(string $root, ?string $fstype = 'cifs', string $source = self::SOURCE): string
    {
        $file = storage_path('framework/testing/mountinfo-'.getmypid());
        $point = str_replace(' ', '\\040', rtrim(str_replace('\\', '/', $root), '/'));
        $lines = ['22 1 8:1 / / rw,relatime shared:1 - ext4 /dev/sda1 rw'];
        if ($fstype !== null) {
            // mountinfo escapa la barra invertida y el espacio en octal.
            $escaped = str_replace(['\\', ' '], ['\\134', '\\040'], $source);
            $lines[] = "98 22 0:52 / {$point} rw,relatime shared:60 - {$fstype} {$escaped} rw,vers=3.1.1,cache=strict,username=mlhabitat";
        }
        (new Files)->ensureDirectoryExists(dirname($file));
        file_put_contents($file, implode("\n", $lines)."\n");

        config([
            'filesystems.disks.media_synology.root' => str_replace('\\', '/', $root),
            'media.synology.mountinfo' => $file,
            'media.synology.source' => self::SOURCE,
            'media.synology.reachability_check' => false,
        ]);
        SynologyMount::flush();

        return $file;
    }

    /** Todos los archivos (incluidos ocultos) bajo la carpeta del NAS. */
    public function files(): array
    {
        return array_map(
            fn ($f) => str_replace('\\', '/', $f->getRelativePathname()),
            (new Files)->allFiles($this->root, true),
        );
    }

    private function guard(): void
    {
        $this->calls++;
        if ($this->down) {
            throw new SynologyUnavailableException('El NAS no responde en la red (Tailscale).');
        }
    }

    private function guardWrite(string $path): void
    {
        $this->guard();
        if ($this->failWrites) {
            throw UnableToWriteFile::atLocation($path, 'Permiso denegado');
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
        $this->guardWrite($path);
        if ($this->failMidWrite) {
            $this->inner->write($path, substr($contents, 0, (int) (strlen($contents) / 2)), $config);
            throw UnableToWriteFile::atLocation($path, 'Conexión interrumpida');
        }
        $this->inner->write($path, $this->corruptWrites ? strrev($contents) : $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->guardWrite($path);
        if ($this->failMidWrite || $this->corruptWrites) {
            $data = (string) stream_get_contents($contents);
            $this->write($path, $data, $config);

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
