<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with contenido editorial real
     * (categorías, textos de inicio, apariencia, páginas y proveedores de
     * almacenamiento). No crea ninguna cuenta de usuario: el primer
     * administrador se crea con `php artisan mrinsight:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            ThemeSettingsSeeder::class,
            SiteSettingsSeeder::class,
            HomepageSectionSeeder::class,
            PageSeeder::class,
            StorageProviderSeeder::class,
            MediaCollectionSeeder::class,
            StudioPageSeeder::class,
        ]);
    }
}
