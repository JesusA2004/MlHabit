<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();

            // Ruta pública visitada (p. ej. "/", "/tips/mi-articulo").
            $table->string('path', 2048);

            // Publicación asociada cuando la visita es un tip; permite el
            // ranking de "lo más visto" sin depender de parsear la ruta.
            $table->foreignId('post_id')->nullable()->references('id')->on('posts')->nullOnDelete();

            // Clave de la página de Studio (home, mi-historia…) cuando aplica.
            $table->string('studio_page_key')->nullable();

            // Etiqueta legible de la pantalla (para listados de "páginas vistas").
            $table->string('label')->nullable();

            // Host de procedencia (sin query ni ruta): mide referidores de forma
            // agregada sin almacenar URLs completas de terceros.
            $table->string('referrer_host')->nullable();

            // Identificador anónimo y rotativo del visitante: sha256(ip + UA +
            // fecha + secreto de la app). NO se guarda IP ni cookie alguna.
            $table->string('visitor_hash', 64)->index();

            // true en la primera vista de ese visitante a esa ruta en el día.
            $table->boolean('is_unique')->default(false);

            // Fecha de la visita (para agregados diarios eficientes).
            $table->date('viewed_on');

            $table->timestamp('created_at')->useCurrent();

            $table->index('post_id');
            $table->index('studio_page_key');
            $table->index('viewed_on');
            $table->index(['viewed_on', 'is_unique']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
