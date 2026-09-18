<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen gehören zu einem Modul.
 *
 * Ein Modul liefert seine Rollen über das Manifest (`->rolle(...)`);
 * `modules:sync` trägt hier den Modul-Key ein. `modul` = null heißt: von Hand
 * im Panel angelegt oder System-Rolle.
 *
 * Nicht zu verwechseln mit `quelle`: die sagt, wer die MITGLIEDER pflegt
 * (ein Abgleich), `modul` sagt, wem die ROLLE gehört.
 *
 * `plattformweit` markiert Modulrollen, die überall gebraucht werden
 * (Schüler, Lehrer, Eltern …) – sie stehen bei jedem Modul zur Auswahl.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'modul')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('modul', 64)->nullable()->after('quelle')->index();
            });
        }

        if (! Schema::hasColumn('roles', 'plattformweit')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('plattformweit')->default(false)->after('modul');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'plattformweit')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('plattformweit');
            });
        }

        if (Schema::hasColumn('roles', 'modul')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex(['modul']);
                $table->dropColumn('modul');
            });
        }
    }
};
