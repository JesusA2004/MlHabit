<?php

namespace Database\Seeders;

use App\Studio\StudioService;
use Illuminate\Database\Seeder;

/**
 * Crea las páginas del sistema en Studio (inicio, mi-historia, mi-proceso,
 * redes) a partir del contenido ya sembrado. Es idempotente: no toca
 * páginas existentes. Si no se ejecuta, se crean al primer acceso.
 */
class StudioPageSeeder extends Seeder
{
    public function run(StudioService $studio): void
    {
        $studio->ensureSystemPages();
    }
}
