<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            // Siguiente fragmento esperado: rechaza fragmentos duplicados o
            // fuera de orden y permite reanudar tras recargar la página.
            $table->unsignedInteger('next_chunk_index')->default(0)->after('chunk_size');
            // Checksum SHA-256 opcional calculado en el navegador: si el
            // cliente lo envía, se verifica contra el archivo ensamblado
            // antes de marcar la subida como completa.
            $table->string('checksum_sha256', 64)->nullable()->after('next_chunk_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['next_chunk_index', 'checksum_sha256']);
        });
    }
};
