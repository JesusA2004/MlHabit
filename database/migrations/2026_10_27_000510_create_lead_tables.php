<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospectos (CRM) con origen/atribución y bitácora comercial propia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio', 24)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->char('postal_code', 5)->nullable()->index();
            $table->string('city', 120)->nullable();
            $table->string('state', 80)->nullable();
            $table->foreignId('coverage_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('budget_range', 32)->nullable();
            $table->string('payment_preference', 32)->nullable();
            $table->string('timeframe', 32)->nullable();
            $table->text('measurements_note')->nullable();
            $table->text('comments')->nullable();
            // Atribución
            $table->string('source', 32)->default('web_form');
            $table->string('form', 64)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 160)->nullable();
            $table->string('utm_term', 160)->nullable();
            $table->string('utm_content', 160)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->string('device_type', 16)->nullable();
            // Seguimiento
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->string('lost_reason', 64)->nullable();
            $table->text('lost_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_to']);
            $table->index('created_at');
        });

        Schema::create('lead_product_category', function (Blueprint $table) {
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->primary(['lead_id', 'product_category_id']);
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_product_category');
        Schema::dropIfExists('leads');
    }
};
