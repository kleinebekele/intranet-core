<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit-Log: wer hat wann was getan.
 *
 * Bisher gab es dafür nur Bruchstücke – das Microsoft-Protokoll, das Maillog,
 * das Systemlog. Wer sich wann angemeldet oder wer einen Benutzer gesperrt hat,
 * stand nirgends. Hier landet das alles in EINER Tabelle, sichtbar unter
 * Verwaltung → Audit. Module schreiben über \App\Support\Audit hinein.
 *
 * Bewusst eine Tabelle statt Logdatei: überlebt das Log-Aufräumen, lässt sich
 * nach Benutzer und Vorgang filtern. Es werden keine Passwörter, Tokens oder
 * Codes gespeichert – nur wer, was, wen, wann und ein knapper Klartext.
 *
 * Dazu bekommt `users` den Zeitpunkt der letzten Anmeldung: Die Frage
 * „nutzt der das Konto überhaupt noch?" soll ein Blick in die Liste beantworten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'zuletzt_angemeldet_am')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('zuletzt_angemeldet_am')->nullable()->after('remember_token');
            });
        }

        if (Schema::hasTable('audit_log')) {
            return;
        }

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Was passiert ist – ein kurzer Schlüssel wie „anmeldung",
            // „benutzer.gesperrt", „rolle.geloescht". Absichtlich Text, kein
            // ENUM: Module bringen eigene Schlüssel mit (Präfix = Modulname).
            $table->string('aktion', 64);

            // Wer es getan hat. Name als Kopie, damit der Eintrag auch nach
            // dem Löschen des Kontos noch lesbar bleibt.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('akteur')->nullable();

            // Wen oder was es betraf (z. B. der gesperrte Benutzer).
            $table->foreignId('betroffener_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('betroffener')->nullable();
            $table->string('ziel', 128)->nullable();   // frei: „Rolle lehrer", „Modul kantine" …

            // Klartext für die Anzeige + optionale Details als JSON
            // (Vorher/Nachher, geänderte Felder – nie Geheimnisse).
            $table->text('beschreibung')->nullable();
            $table->json('daten')->nullable();

            $table->string('ip', 45)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('aktion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');

        if (Schema::hasColumn('users', 'zuletzt_angemeldet_am')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('zuletzt_angemeldet_am');
            });
        }
    }
};
