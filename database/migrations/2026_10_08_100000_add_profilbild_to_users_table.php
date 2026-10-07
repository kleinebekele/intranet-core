<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profilbild je Benutzer: Pfad auf der privaten Disk (storage/app/private),
 * ausgeliefert nur an angemeldete Benutzer über die Route "profilbild".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'profilbild')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('profilbild')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'profilbild')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profilbild');
        });
    }
};
