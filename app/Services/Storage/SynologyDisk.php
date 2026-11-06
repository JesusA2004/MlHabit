<?php

namespace App\Services\Storage;

use App\Enums\StorageProviderKey;
use App\Models\StorageProvider;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Driver `synology` del disco `media_synology`: un SFTP normal cuyas
 * credenciales salen de lo guardado (cifrado) en /admin/almacenamiento,
 * con las variables NAS_SFTP_* de .env como respaldo. La carpeta raíz
 * siempre viene de NAS_SFTP_ROOT (no se edita desde el navegador).
 *
 * El disco se resuelve de forma perezosa: pedir una URL no abre conexión;
 * la conexión SFTP se abre en la primera lectura/escritura.
 */
final class SynologyDisk
{
    public const DISK = 'media_synology';

    /** Huella de la configuración con la que se construyó el disco en este proceso. */
    private static ?string $fingerprint = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public static function create(FilesystemManager $manager, array $config): Filesystem
    {
        $merged = self::mergeProviderConfig($config);
        self::$fingerprint = self::currentFingerprint();

        return $manager->createSftpDriver($merged);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function mergeProviderConfig(array $config): array
    {
        $stored = self::providerConfig();

        foreach (['host' => 'host', 'port' => 'port', 'username' => 'username'] as $from => $to) {
            if (! empty($stored[$from])) {
                $config[$to] = $from === 'port' ? (int) $stored[$from] : $stored[$from];
            }
        }

        $method = $stored['auth_method'] ?? null;
        if ($method === 'private_key' && ! empty($stored['private_key_path'])) {
            $config['privateKey'] = $stored['private_key_path'];
            unset($config['password']);
        } elseif ($method === 'password' && ! empty($stored['password'])) {
            $config['password'] = $stored['password'];
            unset($config['privateKey']);
        }

        $config = array_filter($config, fn ($value) => $value !== null && $value !== '');
        // El proveedor SFTP exige host y usuario como texto aunque aún no se configuren.
        $config['host'] = (string) ($config['host'] ?? '');
        $config['username'] = (string) ($config['username'] ?? '');

        return $config;
    }

    /**
     * Olvida el disco ya resuelto si la configuración cambió desde que se
     * construyó (los workers de cola viven mucho tiempo).
     */
    public static function refreshIfChanged(): void
    {
        $current = self::currentFingerprint();
        if (self::$fingerprint !== null && self::$fingerprint !== $current) {
            Storage::forgetDisk(self::DISK);
            self::$fingerprint = null;
        }
    }

    public static function forget(): void
    {
        Storage::forgetDisk(self::DISK);
        self::$fingerprint = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function providerConfig(): array
    {
        try {
            $provider = StorageProvider::query()->where('key', StorageProviderKey::Synology->value)->first();

            return is_array($provider?->config) ? $provider->config : [];
        } catch (Throwable) {
            // Sin base de datos (instalación, comandos tempranos): solo .env.
            return [];
        }
    }

    private static function currentFingerprint(): string
    {
        try {
            $updated = StorageProvider::query()->where('key', StorageProviderKey::Synology->value)->value('updated_at');

            return (string) $updated;
        } catch (Throwable) {
            return '';
        }
    }
}
