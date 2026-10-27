<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->references('id')->on('media_collections')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('path')->unique();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_collections');
    }
};
