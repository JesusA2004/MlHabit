<?php

namespace Database\Seeders;

use App\Models\StorageProvider;
use Illuminate\Database\Seeder;

class StorageProviderSeeder extends Seeder
{
    public function run(): void
    {
        StorageProvider::query()->updateOrCreate(
            ['key' => 'local'],
            [
                'label' => 'Almacenamiento local',
                'is_active' => true,
                'config' => [
                    'disk' => 'media_local',
                    'root_path' => 'mr-insight',
                ],
            ]
        );

        // El proveedor Synology queda registrado pero inactivo: su conexión
        // real se configurará en una fase posterior (ver /docs/SYNOLOGY_TAILSCALE.md).
        // No se escriben aquí host, usuario, contraseña ni rutas reales.
        StorageProvider::query()->updateOrCreate(
            ['key' => 'synology'],
            [
                'label' => 'Synology NAS (vía Tailscale)',
                'is_active' => false,
                'config' => [
                    'disk' => 'media_synology',
                    'root_path' => null,
                ],
            ]
        );
    }
}
