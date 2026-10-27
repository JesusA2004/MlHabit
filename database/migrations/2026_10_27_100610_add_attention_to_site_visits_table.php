<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo visits: marca de "requiere atención" en una visita. Se usa cuando
 * un pago confirmado no pudo asegurar el horario (apartado vencido y tomado
 * por otra reserva) o llegó para una visita cancelada: el dinero nunca se
 * pierde, pero el equipo debe reprogramar o reembolsar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->string('attention_reason', 200)->nullable()->after('cancel_reason');
            $table->timestamp('attention_at')->nullable()->after('attention_reason')->index();
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropIndex(['attention_at']);
            $table->dropColumn(['attention_reason', 'attention_at']);
        });
    }
};
