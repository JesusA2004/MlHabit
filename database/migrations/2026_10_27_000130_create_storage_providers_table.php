<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(false);
            // Cifrado en el modelo (cast "encrypted:array"): puede contener
            // host, puerto, usuario y ruta de llave privada SFTP.
            $table->text('config')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('last_test_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_providers');
    }
};
