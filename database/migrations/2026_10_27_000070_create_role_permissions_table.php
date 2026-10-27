<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz rol → permiso editable desde Administración. Los valores por
 * defecto viven en App\Support\Permissions\RoleDefaults y se siembran con
 * RolePermissionSeeder. El superadministrador no depende de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 32);
            $table->string('permission', 64);
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
