<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Solo CONFIGURACIÓN necesaria para operar: permisos por rol,
     * proveedores de almacenamiento, colecciones de medios, estructura de
     * secciones de la portada, categorías de catálogo (sin publicar) y
     * borradores de páginas legales (sin publicar). No crea usuarios ni
     * datos comerciales: el primer administrador se crea con
     * `php artisan mlhabitat:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            StorageProviderSeeder::class,
            MediaCollectionSeeder::class,
            ProductCategorySeeder::class,
            SiteSectionSeeder::class,
            LegalPageSeeder::class,
        ]);
    }
}
