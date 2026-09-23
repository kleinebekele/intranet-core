<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen lassen sich im Panel von Hand einem Modul zuordnen.
 *
 * `modul_von_hand` unterscheidet diese Zuordnung von der aus dem Manifest:
 * `modules:sync` gibt nur Manifest-Rollen frei, die ein Modul nicht mehr
 * anmeldet – eine Handzuordnung bleibt stehen. Meldet ein Modul die Rolle
 * später selbst an, übernimmt es sie (die Spalte fällt dann auf false).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'modul_von_hand')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('modul_von_hand')->default(false)->after('modul');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'modul_von_hand')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('modul_von_hand');
            });
        }
    }
};
