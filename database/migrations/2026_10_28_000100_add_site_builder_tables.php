<?php

use App\Cms\Layout\LegacyLayouts;
use App\Enums\SiteSectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Editor visual del sitio: cada sección guarda un lienzo (`layout`) de 12
 * columnas con bloques posicionados; la página tiene un borrador único y un
 * historial de versiones publicadas. Convierte las secciones existentes en
 * lienzos que reproducen la portada actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table) {
            $table->uuid('key')->nullable()->after('id');
            $table->json('layout')->nullable()->after('content');
            $table->unsignedSmallInteger('layout_version')->default(1)->after('layout');
        });

        // Conversión de datos: cada sección tipada recibe un lienzo fiel al
        // diseño actual (bloques de texto + bloque dinámico con sus datos).
        foreach (DB::table('site_sections')->orderBy('id')->get() as $row) {
            $type = SiteSectionType::tryFrom((string) $row->type);
            $content = is_string($row->content) ? json_decode($row->content, true) : null;
            $content = is_array($content) ? $content : [];

            DB::table('site_sections')->where('id', $row->id)->update([
                'key' => (string) Str::uuid(),
                'layout' => $type !== null ? json_encode(LegacyLayouts::for($type, $content), JSON_UNESCAPED_UNICODE) : null,
                'layout_version' => 1,
            ]);
        }

        Schema::table('site_sections', function (Blueprint $table) {
            // Una página puede tener varias secciones del mismo tipo
            // (p. ej. dos lienzos "Texto + imagen").
            $table->index(['page', 'type']);
            $table->dropUnique(['page', 'type']);
            $table->unique('key');
        });

        Schema::create('site_page_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('page', 32)->unique();
            $table->json('snapshot');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('site_page_versions', function (Blueprint $table) {
            $table->id();
            $table->string('page', 32);
            $table->unsignedInteger('number');
            $table->json('snapshot');
            $table->string('note', 300)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['page', 'number']);
            $table->index(['page', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_versions');
        Schema::dropIfExists('site_page_drafts');

        Schema::table('site_sections', function (Blueprint $table) {
            $table->dropUnique(['key']);
        });

        // Si hay secciones repetidas por tipo no se puede volver a la regla
        // anterior: se conservan solo las primeras de cada tipo.
        $keep = DB::table('site_sections')->selectRaw('MIN(id) as id')->groupBy('page', 'type')->pluck('id');
        DB::table('site_sections')->whereNotIn('id', $keep)->delete();
        DB::table('site_sections')->where('type', 'canvas')->delete();

        Schema::table('site_sections', function (Blueprint $table) {
            $table->unique(['page', 'type']);
            $table->dropIndex(['page', 'type']);
            $table->dropColumn(['key', 'layout', 'layout_version']);
        });
    }
};
