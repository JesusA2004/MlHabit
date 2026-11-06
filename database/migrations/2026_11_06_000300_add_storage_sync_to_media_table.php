<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respaldo local y sincronización con el NAS:
 *
 * - `checksum`: huella xxh128 del original, calculada al subir (o al
 *   migrar si no existía). Sirve para verificar copias entre discos.
 * - `pending_sync_to`: disco al que el archivo DEBERÍA estar (p. ej.
 *   `media_synology`) cuando se guardó en el servidor porque el NAS no
 *   respondió. `media:sync-pending` lo empuja en cuanto el NAS conecte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('size');
            $table->string('pending_sync_to', 32)->nullable()->after('status');
            $table->unsignedSmallInteger('sync_attempts')->default(0)->after('pending_sync_to');
            $table->string('sync_error', 500)->nullable()->after('sync_attempts');

            $table->index('pending_sync_to');
            $table->index('disk');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['pending_sync_to']);
            $table->dropIndex(['disk']);
            $table->dropColumn(['checksum', 'pending_sync_to', 'sync_attempts', 'sync_error']);
        });
    }
};
