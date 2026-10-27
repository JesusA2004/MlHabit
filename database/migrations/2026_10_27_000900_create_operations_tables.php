<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedores, técnicos, fabricación, tarifarios versionados, instalación,
 * calidad, pagos a proveedores, incidencias y garantías.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('legal_name', 200)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('type', 16);
            $table->string('status', 16)->default('active')->index();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('capabilities')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('position', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('coverage_zone_supplier', function (Blueprint $table) {
            $table->foreignId('coverage_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['coverage_zone_id', 'supplier_id']);
        });

        Schema::create('supplier_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('timeliness_score');
            $table->unsignedTinyInteger('communication_score');
            $table->text('comments')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->boolean('does_visits')->default(true);
            $table->boolean('does_installations')->default(false);
            $table->text('skills')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fabrication_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('design_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->decimal('cost', 14, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->unsignedInteger('version');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->date('effective_from')->nullable();
            $table->decimal('mobilization_fee', 14, 2)->default(0);
            $table->decimal('minimum_charge', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('rate_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('description', 300);
            $table->string('unit', 24);
            $table->decimal('unit_price', 14, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['rate_card_id', 'code']);
        });

        Schema::create('installation_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_installer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            // Congelados desde el tarifario usado.
            $table->decimal('mobilization_fee', 14, 2)->default(0);
            $table->decimal('minimum_charge', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('client_conformity', 16)->nullable();
            $table->text('client_comment')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->foreignId('client_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('evidence_submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('installation_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_card_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('description', 300);
            $table->string('unit', 24);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->boolean('is_extra')->default(false);
            $table->foreignId('extra_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('extra_approved_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installation_order_item_id')->nullable()->constrained()->nullOnDelete();
            // installer: autoevaluación con evidencia; quality: revisión interna.
            $table->string('stage', 16);
            $table->string('result', 16)->default('pending');
            $table->text('observations')->nullable();
            $table->boolean('client_contacted')->default(false);
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quality_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_inspection_id')->constrained()->cascadeOnDelete();
            $table->string('criterion', 32);
            $table->string('result', 8);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['quality_inspection_id', 'criterion']);
        });

        Schema::create('supplier_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('installation_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('fabrication_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 24)->default('pending_authorization')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('rejection_reason', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('installation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 32);
            $table->string('severity', 16);
            $table->string('origin', 16);
            $table->string('status', 16)->default('open')->index();
            $table->string('title', 200);
            $table->text('description');
            $table->string('responsible_party', 16)->nullable();
            $table->foreignId('responsible_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('action_plan')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('visible_to_client')->default(true);
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('scope');
            $table->text('exclusions')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 24)->unique();
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('status', 16)->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('supplier_payouts');
        Schema::dropIfExists('quality_check_items');
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('installation_order_items');
        Schema::dropIfExists('installation_orders');
        Schema::dropIfExists('rate_card_items');
        Schema::dropIfExists('rate_cards');
        Schema::dropIfExists('fabrication_orders');
        Schema::dropIfExists('technician_profiles');
        Schema::dropIfExists('supplier_evaluations');
        Schema::dropIfExists('coverage_zone_supplier');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
