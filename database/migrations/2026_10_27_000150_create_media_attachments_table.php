<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula archivos de la biblioteca (públicos o privados) con entidades del
 * dominio: galerías de catálogo, fotos de un prospecto, croquis de medición,
 * renders de diseño, evidencias de instalación, documentos de proyecto…
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->morphs('attachable');
            $table->string('role', 32)->default('gallery');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['media_id', 'attachable_type', 'attachable_id', 'role'], 'media_attachments_unique');
            $table->index(['attachable_type', 'attachable_id', 'role', 'sort'], 'media_attachments_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
