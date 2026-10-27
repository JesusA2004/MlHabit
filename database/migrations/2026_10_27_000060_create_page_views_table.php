<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analítica anónima del sitio público: sin cookies ni IP. El visitante se
 * identifica con un hash diario rotativo (ver TrackPageView).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 2048);
            $table->string('label')->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('visitor_hash', 64)->index();
            $table->boolean('is_unique')->default(false);
            $table->date('viewed_on');
            $table->timestamp('created_at')->useCurrent();

            $table->index('viewed_on');
            $table->index(['viewed_on', 'is_unique']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
