<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recorridos guiados: qué recorrido terminó u omitió cada persona (para no
 * volver a ofrecer el recorrido general en cada inicio de sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tour_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour', 80);
            $table->string('status', 16);
            $table->unsignedSmallInteger('last_step')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tour_states');
    }
};
