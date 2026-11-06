<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traslados de la biblioteca multimedia entre discos (servidor ⇄ Synology).
 * Cada archivo (original + variantes) es un renglón en
 * `storage_migration_items`, lo que permite reanudar, reintentar fallidos
 * y mostrar el progreso real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_migrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('source_disk', 32);
            $table->string('target_disk', 32);
            $table->boolean('keep_source')->default(true);
            $table->string('status', 32)->default('queued');
            $table->string('batch_id', 36)->nullable();
            $table->foreignId('started_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('sources_purged_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('storage_migration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_migration_id')->references('id')->on('storage_migrations')->cascadeOnDelete();
            $table->foreignId('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('files')->default(1);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedBigInteger('transferred_bytes')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            // La copia del disco origen sigue ahí (se conservó a propósito).
            $table->boolean('source_kept')->default(false);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_migration_id', 'media_id']);
            $table->index(['storage_migration_id', 'status']);
            $table->index(['media_id', 'source_kept']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_migration_items');
        Schema::dropIfExists('storage_migrations');
    }
};
