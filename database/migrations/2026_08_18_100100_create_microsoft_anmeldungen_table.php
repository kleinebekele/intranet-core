<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll aller Anmeldeversuche über Microsoft.
 *
 * Ohne dieses Protokoll ist ein gescheiterter SSO-Login eine Blackbox: Der
 * Benutzer sieht nur "hat nicht geklappt", und der Admin müsste ins Logfile.
 * Hier steht stattdessen je Versuch, wer es war und woran es lag - sichtbar
 * unter Verwaltung -> Microsoft-SSO.
 *
 * Bewusst eine eigene Tabelle statt Logdatei: Sie überlebt das Log-Aufräumen
 * und lässt sich filtern. Es werden keine Tokens gespeichert, nur Kennung,
 * Adresse und Ergebnis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('microsoft_anmeldungen', function (Blueprint $table) {
            $table->id();

            // Wer es war (soweit Microsoft es verraten hat).
            $table->string('email')->nullable();
            $table->string('microsoft_id', 64)->nullable();
            $table->string('name')->nullable();

            // Wie es ausgegangen ist: angemeldet, neu_angelegt, kein_konto,
            // keine_gruppe, gesperrt, fehler. Absichtlich als Text und nicht
            // als ENUM - ein weiterer Ausgang darf ohne Migration dazukommen.
            $table->string('ergebnis', 32);
            $table->text('meldung')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index('ergebnis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('microsoft_anmeldungen');
    }
};
