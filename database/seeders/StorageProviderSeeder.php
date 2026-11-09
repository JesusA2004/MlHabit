<?php

namespace Database\Seeders;

use App\Models\StorageProvider;
use Illuminate\Database\Seeder;

/**
 * Proveedores de almacenamiento multimedia de ML Habitat. Local queda
 * activo; Synology (NAS montado por SMB/CIFS vía Tailscale) se registra
 * inactivo y se activa desde /admin/almacenamiento tras una prueba correcta.
 * Nunca se escriben credenciales aquí (el montaje lo gestiona Linux).
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
                'label' => 'SynologyExtra (SMB vía Tailscale)',
                'is_active' => false,
                'config' => [
                    'disk' => 'media_synology',
                ],
            ]
        );
    }
}
