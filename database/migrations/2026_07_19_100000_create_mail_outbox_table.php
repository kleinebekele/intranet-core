<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgangskorb für alle E-Mails der Plattform – zugleich Versand-Protokoll.
 *
 * Jede Mail landet hier, statt sofort rauszugehen; ein Task (`mail:ausliefern`)
 * holt sie im erlaubten Takt heraus. Damit ist beides gelöst: die Drosselung
 * (manche Provider erlauben nur wenige hundert Mails je Stunde) und die Frage
 * „ist die Mail eigentlich rausgegangen?", die Laravel von sich aus nicht
 * beantworten kann.
 *
 * Die Zeile bleibt nach dem Versand als Protokolleintrag stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->id();

            // wartend | versendet | fehlgeschlagen
            $table->string('status', 20)->default('wartend');

            // Höher = eiliger. Zeitkritisches (2FA, Passwort zurücksetzen) bekommt
            // Vorfahrt, damit es nicht hinter einem Rundmail-Schwall hängt.
            $table->unsignedTinyInteger('prioritaet')->default(0);

            $table->string('mailer', 60)->nullable();   // Name des Laravel-Mailers
            $table->string('betreff')->nullable();
            $table->json('an')->nullable();             // ["a@b.de", …] – nur zur Anzeige/Suche
            $table->string('quelle')->nullable();       // Mailable- oder Notification-Klasse

            // Die vollständige Symfony-Nachricht, serialisiert. Nur so lässt sie
            // sich später unverändert ausliefern (inkl. Anhängen und Kopfzeilen).
            $table->longText('nachricht');

            $table->unsignedTinyInteger('versuche')->default(0);
            $table->text('fehler')->nullable();
            $table->string('message_id')->nullable();   // Schlüssel zu späteren Provider-Rückmeldungen
            $table->timestamp('versendet_am')->nullable();

            $table->timestamps();

            // Der Auslieferungs-Task fragt genau so: offene Posten, eilige zuerst.
            $table->index(['status', 'prioritaet', 'id']);

            // Für die Stundenzählung des Limits.
            $table->index('versendet_am');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_outbox');
    }
};
