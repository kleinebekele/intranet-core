<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('externe_id')->nullable()->after('id');   // ID aus dem Quellsystem (CSV)
            $table->string('source')->nullable()->after('is_admin'); // 'import' | null (selbst registriert)
        });

        // Importierte Nutzer haben zunächst kein Passwort – sie setzen es per Einladungslink.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['externe_id', 'source']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
