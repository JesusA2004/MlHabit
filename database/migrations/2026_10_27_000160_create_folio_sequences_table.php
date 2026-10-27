<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consecutivos de folios por prefijo y año (MLH-L-2026-00001…). Se
 * incrementan con SELECT … FOR UPDATE dentro de una transacción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 8);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_sequences');
    }
};
