<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asesoría inicial remota y visita técnica a domicilio (con política
 * versionada de tarifa/acreditación congelada en cada visita).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 24)->default('scheduled')->index();
            $table->string('channel', 16)->default('video');
            // Integración futura de videollamada: solo proveedor + URL.
            $table->string('meeting_provider', 32)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->text('outcome_notes')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['advisor_id', 'starts_at']);
        });

        Schema::create('visit_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->decimal('fee_amount', 14, 2);
            $table->char('currency', 3)->default('MXN');
            $table->boolean('is_creditable')->default(false);
            $table->string('credit_type', 16)->nullable();
            $table->decimal('credit_value', 14, 2)->nullable();
            $table->unsignedSmallInteger('credit_validity_days')->nullable();
            $table->text('credit_conditions')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->unsignedSmallInteger('buffer_minutes')->default(30);
            $table->unsignedSmallInteger('hold_minutes')->default(15);
            $table->unsignedSmallInteger('min_notice_hours')->default(24);
            $table->unsignedSmallInteger('booking_window_days')->default(30);
            $table->text('scope');
            $table->text('cancellation_policy');
            $table->text('reschedule_policy');
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('address_id')->constrained()->restrictOnDelete();
            $table->foreignId('coverage_zone_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('visit_policy_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 24)->default('pending_payment')->index();
            // Congelado desde la política vigente al reservar.
            $table->decimal('fee_amount', 14, 2);
            $table->char('currency', 3)->default('MXN');
            $table->boolean('is_creditable')->default(false);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->text('access_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('site_visits')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['technician_id', 'starts_at']);
            $table->index(['status', 'hold_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('visit_policies');
        Schema::dropIfExists('consultations');
    }
};
