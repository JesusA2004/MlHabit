<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->string('title');
            $table->date('entry_date')->nullable();
            $table->json('body')->nullable();
            $table->foreignId('cover_media_id')->nullable()->references('id')->on('media')->nullOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['page_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_entries');
    }
};
