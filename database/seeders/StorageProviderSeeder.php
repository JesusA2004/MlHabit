<?php

namespace Database\Seeders;

use App\Models\StorageProvider;
use Illuminate\Database\Seeder;

/**
 * Proveedores de almacenamiento multimedia de ML Habitat. Local queda
 * activo; Synology (SFTP vía Tailscale) se registra inactivo y se configura
 * desde /admin/almacenamiento. Nunca se escriben credenciales aquí.
 * Idempotente: no pisa configuración existente.
 */
class StorageProviderSeeder extends Seeder
{
    public function run(): void
    {
        StorageProvider::query()->firstOrCreate(
            ['key' => 'local'],
            [
                'label' => 'Almacenamiento local',
                'is_active' => true,
                'config' => [
                    'disk' => 'media_local',
                ],
            ]
        );

        StorageProvider::query()->firstOrCreate(
            ['key' => 'synology'],
            [
                'label' => 'Synology NAS (vía Tailscale)',
                'is_active' => false,
                'config' => [
                    'disk' => 'media_synology',
                ],
            ]
        );
    }
}
