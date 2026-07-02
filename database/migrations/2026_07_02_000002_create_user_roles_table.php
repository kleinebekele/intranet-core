<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verbindungstabelle (n:n): ein User hat viele Rollen, eine Rolle viele User.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_id', 64);
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnDelete();

            // Jede User-Rollen-Kombination darf nur einmal existieren.
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
