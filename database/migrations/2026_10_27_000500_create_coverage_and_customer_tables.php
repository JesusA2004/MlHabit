<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobertura operativa (zonas, códigos postales, técnicos asignados) y
 * clientes con sus domicilios. La cobertura publicitaria se edita aparte en
 * el CMS; el cobro de visitas solo valida contra estas tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('state', 80);
            $table->string('municipality', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('visits_enabled')->default(true);
            // Radio opcional alrededor de un punto base operativo.
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->decimal('radius_km', 6, 2)->nullable();
            $table->unsignedSmallInteger('travel_buffer_minutes')->default(30);
            $table->unsignedSmallInteger('max_visits_per_day')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'state']);
        });

        Schema::create('coverage_postal_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_zone_id')->constrained()->cascadeOnDelete();
            $table->char('postal_code', 5)->unique();
            $table->timestamps();
        });

        Schema::create('coverage_zone_user', function (Blueprint $table) {
            $table->foreignId('coverage_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['coverage_zone_id', 'user_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 20)->nullable()->index();
            $table->string('preferred_contact', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('street', 160);
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood', 120);
            $table->char('postal_code', 5)->index();
            $table->string('municipality', 120);
            $table->string('state', 80);
            $table->text('references')->nullable();
            $table->foreignId('coverage_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('coverage_zone_user');
        Schema::dropIfExists('coverage_postal_codes');
        Schema::dropIfExists('coverage_zones');
    }
};
