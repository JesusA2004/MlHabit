<?php

use App\Cms\Layout\HomeRedesign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publica la portada rediseñada (v3) en instalaciones existentes. Antes de
 * cambiar nada respalda la portada publicada como versión del historial
 * («Portada anterior (antes del rediseño)»); solo reemplaza una portada de
 * fábrica o nunca publicada por el equipo, y nunca toca páginas propias.
 * Idempotente (ver App\Cms\Layout\HomeRedesign). Sin `down`: el respaldo se
 * restaura desde el historial del editor visual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_sections') || ! Schema::hasTable('site_page_versions')) {
            return;
        }

        // Instalación nueva (sin portada): la siembra la crea ya con el
        // diseño nuevo; aquí no se inventa nada.
        if (! DB::table('site_sections')->where('page', HomeRedesign::PAGE)->exists()) {
            return;
        }

        app(HomeRedesign::class)->apply();
    }

    public function down(): void
    {
        // El respaldo vive en el historial del editor (site_page_versions).
    }
};
