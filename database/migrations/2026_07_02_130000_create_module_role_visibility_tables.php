<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Welche Rollen dürfen ein Modul in der Navigation sehen?
        // (keine Zeile für ein Modul = für alle sichtbar)
        Schema::create('module_role', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('role_id', 64);
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
            $table->primary(['module_id', 'role_id']);
        });

        // Welche Rollen dürfen einen Unterpunkt sehen?
        Schema::create('module_menu_item_role', function (Blueprint $table) {
            $table->foreignId('module_menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('role_id', 64);
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();
            $table->primary(['module_menu_item_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_menu_item_role');
        Schema::dropIfExists('module_role');
    }
};
