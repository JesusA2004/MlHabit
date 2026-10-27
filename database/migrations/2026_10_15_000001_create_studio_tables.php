<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR. INSIGHT Studio: páginas compuestas por bloques estructurados (JSON
 * validado contra resources/js/studio/schema.json), con borrador editable,
 * control de concurrencia por `draft_revision` e historial de versiones
 * publicadas restaurables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('kind', 20)->default('custom');
            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->json('draft')->nullable();
            $table->unsignedInteger('draft_revision')->default(0);
            $table->foreignId('draft_updated_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('draft_updated_at')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('studio_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_page_id')->constrained('studio_pages')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('kind', 20)->default('publish');
            $table->json('document');
            $table->string('summary')->nullable();
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['studio_page_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_versions');
        Schema::dropIfExists('studio_pages');
    }
};
