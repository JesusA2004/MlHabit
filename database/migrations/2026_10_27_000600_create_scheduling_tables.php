<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda: disponibilidad recurrente, excepciones (bloqueos, vacaciones,
 * horario extra) y reservas. Dos agendas comparten el modelo, separadas por
 * `kind`: consultation (asesores, remoto) y visit (técnicos, por zona).
 *
 * Doble reserva: `slot_key` (usuario|inicio) es UNIQUE mientras la reserva
 * está activa y se pone a NULL al liberarla. Además, BookingService bloquea
 * la fila del usuario (FOR UPDATE) y verifica traslapes con buffer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'kind', 'weekday']);
        });

        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->nullable();
            $table->string('type', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
        });

        Schema::create('calendar_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->nullableMorphs('bookable');
            $table->string('status', 16)->default('held');
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('slot_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status', 'starts_at']);
            $table->index(['status', 'hold_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_bookings');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_rules');
    }
};
