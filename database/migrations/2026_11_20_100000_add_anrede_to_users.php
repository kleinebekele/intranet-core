<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Briefanrede einer Person als fertiger Text, z. B. „Frau Dr. Muster" – gesetzt
 * von einem Import (Waldorf: Linear `Anrede` + `Anredetitel` + `Nachname`).
 * Leer = unbekannt; Mails fallen dann auf eine neutrale Anrede zurück.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'anrede')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('anrede', 150)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'anrede')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('anrede');
            });
        }
    }
};
