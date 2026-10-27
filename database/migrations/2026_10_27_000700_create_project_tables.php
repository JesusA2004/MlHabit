<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proyecto del cliente y todo lo que cuelga de él antes del contrato:
 * precalificación, medición (versionada), diseño (versionado) y
 * cotización (versionada). El proyecto nace cuando el prospecto pasa a
 * medición/diseño; queda "contratado" al aceptar cotización y firmar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prequalifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->decimal('requested_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('preferred_term_months')->nullable();
            $table->string('income_range', 32)->nullable();
            $table->string('employment_type', 32)->nullable();
            $table->text('applicant_notes')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('status', 32)->default('preparation')->index();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operations_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_install_date')->nullable();
            $table->timestamp('contracted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('site_visits', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });

        Schema::create('project_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('title', 200);
            $table->text('body')->nullable();
            // client: visible en el portal. internal: solo equipo.
            $table->string('visibility', 16)->default('internal');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['project_id', 'occurred_at']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft');
            $table->string('space_type', 64)->nullable();
            $table->text('access_notes')->nullable();
            $table->text('existing_installations')->nullable();
            $table->text('general_notes')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('measurements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
        });

        Schema::create('measurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measurement_id')->constrained()->cascadeOnDelete();
            $table->string('area', 80);
            $table->string('kind', 32);
            $table->string('label', 160)->nullable();
            // Milímetros: sin flotantes.
            $table->unsignedInteger('value_mm')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('design_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('measurement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('client_approved_layout')->default(false);
            $table->boolean('client_approved_finishes')->default(false);
            $table->timestamp('client_decided_at')->nullable();
            $table->foreignId('client_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('client_comment')->nullable();
            $table->timestamp('technical_approved_at')->nullable();
            $table->foreignId('technical_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('technical_notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft')->index();
            $table->string('title', 160);
            $table->date('valid_until')->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->boolean('prices_include_tax')->default(true);
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('discount_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accepted_ip', 45)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('code', 40)->nullable();
            $table->string('description', 500);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 24);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('pending_deposit')->index();
            // Valores congelados al contratar: no cambian con la configuración.
            $table->decimal('contract_amount', 14, 2);
            $table->decimal('deposit_percent', 5, 2);
            $table->decimal('deposit_amount', 14, 2);
            $table->decimal('visit_credit_amount', 14, 2)->default(0);
            $table->decimal('deposit_due_amount', 14, 2);
            $table->char('currency', 3)->default('MXN');
            $table->text('terms_snapshot')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('deposit_paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('design_versions');
        Schema::dropIfExists('measurement_items');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('project_events');
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('projects');
        Schema::dropIfExists('prequalifications');
    }
};
