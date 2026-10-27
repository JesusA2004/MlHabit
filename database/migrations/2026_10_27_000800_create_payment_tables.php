<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos auditables. Nunca se guardan PAN ni CVV: solo IDs del proveedor.
 * Ninguna fila financiera se borra (sin soft deletes; FK restrict).
 *
 * Idempotencia:
 *  - payments.order_id UNIQUE (también es el order_id enviado a Openpay).
 *  - payments.idempotency_key UNIQUE (clave de la intención de cobro).
 *  - payment_attempts.idempotency_key UNIQUE (un intento por envío).
 *  - webhook_events.dedupe_key UNIQUE (mismo evento → una fila).
 *  - refunds.idempotency_key UNIQUE.
 *  - visit_credits.site_visit_id UNIQUE y contract_id UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->string('order_id', 64)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_visit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('concept', 24);
            $table->string('description', 250);
            $table->string('method', 24);
            $table->string('provider', 24);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('MXN');
            $table->string('status', 24)->default('pending')->index();
            $table->string('provider_transaction_id', 64)->nullable()->unique();
            $table->string('provider_authorization', 64)->nullable();
            $table->string('card_brand', 24)->nullable();
            $table->char('card_last4', 4)->nullable();
            $table->decimal('fee_amount', 14, 2)->nullable();
            $table->decimal('net_amount', 14, 2)->nullable();
            $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->string('error_code', 16)->nullable();
            $table->string('error_message', 300)->nullable();
            $table->string('redirect_url', 1000)->nullable();
            $table->string('reference', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['concept', 'status']);
            $table->index('paid_at');
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 24);
            $table->string('provider_transaction_id', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 16)->nullable();
            $table->string('error_message', 300)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'number']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->string('type', 48);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'created_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24);
            $table->string('event_type', 64);
            $table->string('dedupe_key', 128)->unique();
            $table->string('transaction_id', 64)->nullable()->index();
            $table->json('payload');
            $table->string('status', 16)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['provider', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->decimal('amount', 14, 2);
            $table->string('reason', 300);
            $table->string('status', 16)->default('pending');
            $table->string('provider_refund_id', 64)->nullable()->unique();
            $table->string('error_code', 16)->nullable();
            $table->string('error_message', 300)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('visit_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('available')->index();
            $table->date('expires_on')->nullable();
            $table->foreignId('contract_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_credits');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
