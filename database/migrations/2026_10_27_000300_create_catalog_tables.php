<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo público: categorías (cocinas, clósets…), líneas de producto,
 * acabados, etiquetas y proyectos de inspiración. Las galerías se vinculan
 * con media_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort']);
        });

        Schema::create('finishes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('material', 120)->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('summary', 400)->nullable();
            $table->longText('description')->nullable();
            // Listas editoriales cortas ({title, body}); no son entidades del dominio.
            $table->json('benefits')->nullable();
            $table->json('features')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->decimal('price_from', 14, 2)->nullable();
            $table->decimal('price_to', 14, 2)->nullable();
            $table->boolean('show_price')->default(false);
            $table->string('status', 16)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'product_category_id', 'sort']);
        });

        Schema::create('finish_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finish_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'finish_id']);
        });

        Schema::create('product_tag', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::create('showcase_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('summary', 400)->nullable();
            $table->longText('story')->nullable();
            // Solo si el cliente lo autorizó; nunca se rellena automáticamente.
            $table->string('location_label', 120)->nullable();
            // real_photo: trabajo entregado. render: propuesta conceptual.
            $table->string('origin', 16)->default('render');
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured', 'sort']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 32)->default('general');
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 300);
            $table->text('answer');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'topic', 'sort']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name', 120);
            $table->string('author_context', 160)->nullable();
            $table->text('quote');
            $table->foreignId('showcase_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            // Sin consentimiento registrado no se puede publicar.
            $table->timestamp('consent_obtained_at')->nullable();
            $table->string('consent_note')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('showcase_projects');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('finish_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('finishes');
        Schema::dropIfExists('product_categories');
    }
};
