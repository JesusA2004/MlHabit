<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Disco físico para el proveedor de almacenamiento "local" de la
        // biblioteca multimedia de MR. INSIGHT. La entrega al navegador no
        // usa el symlink público de Laravel: pasa por un controlador propio
        // (ver MediaStreamController, Fase 6) para poder soportar peticiones
        // Range en video y, más adelante, servir igual desde el NAS Synology.
        'media_local' => [
            'driver' => 'local',
            'root' => storage_path('app/media'),
            'url' => '/media',
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Proveedor Synology NAS, alcanzable únicamente a través de una red
        // Tailscale privada (ver /docs/SYNOLOGY_TAILSCALE.md). NAS_SFTP_HOST
        // debe ser el nombre MagicDNS del NAS dentro del tailnet, nunca una
        // IP pública. Mientras estas variables no existan en .env, este
        // disco queda definido pero sin credenciales (no se escriben aquí
        // valores de ejemplo) y el proveedor "synology" permanece inactivo
        // (ver StorageProviderSeeder).
        'media_synology' => [
            'driver' => 'sftp',
            'host' => env('NAS_SFTP_HOST'),
            'port' => (int) env('NAS_SFTP_PORT', 22),
            'username' => env('NAS_SFTP_USERNAME'),
            'password' => env('NAS_SFTP_PASSWORD'),
            'privateKey' => env('NAS_SFTP_PRIVATE_KEY_PATH'),
            'root' => env('NAS_SFTP_ROOT', '/mr-insight'),
            'timeout' => 10,
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
