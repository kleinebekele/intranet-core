<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMTP-Absender: eigene Postfächer, über die eine Mail rausgehen kann – mit
 * eigenem Server-Zugang, eigener Absenderadresse, Anzeigename und Antwort-an.
 *
 * Beispiel: Ein Rundbrief der Redaktion soll von redaktion@… kommen und über
 * deren Postfach laufen (damit SPF/DKIM stimmen und Antworten dort landen),
 * die Systemmails weiter über den Standard-Mailer der Instanz.
 *
 * Wer ein Konto benutzen will, markiert die Mail mit seinem Mailer-Namen
 * (siehe \App\Models\MailKonto::anMail); der Ausgangskorb schickt sie dann über
 * genau diesen Zugang. Ohne Markierung bleibt alles beim Standard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_konten')) {
            return;
        }

        Schema::create('mail_konten', function (Blueprint $table) {
            $table->id();

            $table->string('bezeichnung');                  // „Redaktion", „Schulleitung" …
            $table->string('absender_mail');                // From-Adresse dieses Kontos
            $table->string('absender_name')->nullable();    // Vorgabe für den Anzeigenamen
            $table->string('antwort_an')->nullable();       // Vorgabe für Reply-To

            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('verschluesselung', 10)->default('tls'); // tls | ssl | keine
            $table->string('benutzername')->nullable();
            $table->text('passwort')->nullable();           // verschlüsselt (Model-Cast)

            $table->boolean('aktiv')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_konten');
    }
};
