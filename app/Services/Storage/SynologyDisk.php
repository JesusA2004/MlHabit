<?php

namespace App\Services\Storage;

use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

/**
 * Driver `synology-mount` del disco `media_synology`: un disco LOCAL sobre
 * el punto de montaje SMB/CIFS del NAS (NAS_MOUNT_PATH, por defecto
 * /mnt/mlhabitat-storage), envuelto en MountGuardAdapter para que nunca
 * escriba en el directorio vacío del servidor si el NAS se desmonta.
 *
 * El directorio raíz nunca se crea desde aquí (lazy_root_creation): lo crea
 * el montaje. `path()` sigue devolviendo la ruta local (FFmpeg la usa).
 */
final class SynologyDisk
{
    public const DISK = 'media_synology';

    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(array $config): LocalFilesystemAdapter
    {
        $config['root'] = (string) ($config['root'] ?? '/mnt/mlhabitat-storage');

        $visibility = PortableVisibilityConverter::fromArray(
            (array) ($config['permissions'] ?? []),
            (string) ($config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE),
        );

        $local = new LocalAdapter(
            $config['root'],
            $visibility,
            LOCK_EX,
            LocalAdapter::DISALLOW_LINKS,
            lazyRootCreation: true,
        );

        $guard = new MountGuardAdapter($local, app(SynologyMount::class));

        $flysystemConfig = array_intersect_key($config, array_flip(['directory_visibility', 'disable_asserts', 'retain_visibility', 'url', 'visibility']));

        return (new LocalFilesystemAdapter(new Filesystem($guard, $flysystemConfig), $guard, $config))->diskName(self::DISK);
    }

    /** Olvida el disco ya resuelto (se vuelve a construir en el próximo uso). */
    public static function forget(): void
    {
        Storage::forgetDisk(self::DISK);
    }
}
