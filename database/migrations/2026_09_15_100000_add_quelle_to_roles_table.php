<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen bekommen eine Herkunft.
 *
 * Rollen sind zugleich Gruppen: „Schüler Klasse 1", „Eltern Klasse 1",
 * Arbeitskreise. Manche pflegt ein nächtlicher Abgleich aus einem Fremdsystem
 * (z. B. Linear), andere pflegt ein Mensch von Hand. Der Abgleich darf nur
 * seine eigenen Rollen füllen und leeren – deshalb steht hier, wem eine Rolle
 * gehört: `quelle` = null heißt manuell, sonst der Schlüssel des Abgleichs.
 *
 * Bei verwalteten Rollen ist die Mitgliederpflege im Panel gesperrt; alles,
 * was man dort einträgt, würde der nächste Lauf ohnehin wieder zurückdrehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'quelle')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('quelle', 64)->nullable()->after('is_system');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'quelle')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('quelle');
            });
        }
    }
};
