<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreignId('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->string('role')->default('gallery');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'media_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
