<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der festgelegte Anmeldeweg eines Benutzers.
 *
 * Ohne Eintrag (null) gilt die Automatik: Wer schon einmal über Microsoft
 * hereinkam, meldet sich ab dann so an. Ein Administrator kann das in der
 * Benutzerverwaltung überschreiben:
 *
 *   'microsoft' – nur Microsoft, auch wenn sich das Konto noch nie so
 *                 angemeldet hat (z. B. für neue Kollegen, die von Anfang an
 *                 über Microsoft kommen sollen).
 *   'passwort'  – Passwort ausdrücklich weiter erlaubt, obwohl das Konto mit
 *                 einem Microsoft-Konto verknüpft ist. Der Rückweg, damit
 *                 niemand versehentlich dauerhaft ausgesperrt bleibt.
 *
 * Länge großzügig: SQLite erzwingt sie lokal nicht, MySQL auf dem Server
 * schon – knapp bemessene Spalten sind hier schon einmal teuer geworden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('anmeldeweg', 32)->nullable()->after('microsoft_angemeldet_am');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('anmeldeweg');
        });
    }
};
