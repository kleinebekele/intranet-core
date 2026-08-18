<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anmeldung mit dem Microsoft-Konto (Entra ID / Microsoft 365).
 *
 * Zwei neue Spalten am Benutzer:
 *
 * - microsoft_id: die unveraenderliche Objekt-ID (oid) des Kontos im Tenant.
 *   Sie ist der eigentliche Anker: Ein Nachname aendert sich, eine
 *   E-Mail-Adresse auch, die oid nicht. Beim ersten Anmelden wird sie am
 *   passenden Intranet-Konto vermerkt (Zuordnung ueber die E-Mail-Adresse),
 *   danach zaehlt nur noch sie.
 *
 * - microsoft_angemeldet_am: wann dieses Konto zuletzt per Microsoft
 *   hereinkam. Nur zur Auskunft im Verwaltungsbereich.
 *
 * Bewusst KEINE Aenderung an `password`: Auch ein reines SSO-Konto behaelt ein
 * (zufaelliges) Passwort in der Spalte. So bleibt der Passwort-Weg als
 * Rueckfalltuer offen, falls Microsoft einmal nicht erreichbar ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_id', 64)->nullable()->unique()->after('email');
            $table->timestamp('microsoft_angemeldet_am')->nullable()->after('microsoft_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['microsoft_id']);
            $table->dropColumn(['microsoft_id', 'microsoft_angemeldet_am']);
        });
    }
};
