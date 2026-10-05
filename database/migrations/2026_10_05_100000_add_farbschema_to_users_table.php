<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Darstellung je Benutzer (Profil → Darstellung): "hell", "dunkel" oder "system"
 * (folgt dem Betriebssystem). Leer heißt hell.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'farbschema')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('farbschema', 20)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'farbschema')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('farbschema');
        });
    }
};
