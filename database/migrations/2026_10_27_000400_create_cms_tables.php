<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CMS del sitio público: secciones estructuradas de la portada (contenido
 * validado por tipo en App\Cms\SectionType) y páginas legales/informativas
 * con número de versión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page', 32)->default('home');
            $table->string('type', 32);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('content');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page', 'type']);
            $table->index(['page', 'is_visible', 'sort']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('summary', 320)->nullable();
            $table->longText('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('site_sections');
    }
};
