<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_collection_id')->nullable()->references('id')->on('media_collections')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('type');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('status')->default('uploading');
            $table->json('metadata')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
