<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->string('role_id', 64)->primary(); // fachlicher Schlüssel, z. B. 'admin', 'teacher'
            $table->string('name');                   // Anzeigename, im Adminpanel pflegbar
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
