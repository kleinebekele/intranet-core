<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigener Absender und Antwort-an je Modul + Auslöser.
 *
 * Beispiel: Die „Zahlungserinnerung" des Aftersales-Portals soll von
 * buchhaltung@… kommen, die „Versandbestätigung" von versand@… – beide aus
 * demselben Modul, aber mit unterschiedlichem Absender.
 *
 * Gespeichert wird nur, was jemand bewusst eingetragen hat: Ohne Zeile gilt
 * weiter der Absender, den das Modul (oder die Instanz) selbst setzt. Ist eine
 * Zeile da, GEWINNT sie – der Ausgangskorb setzt From/Reply-To beim Einliefern
 * entsprechend (siehe \App\Listeners\MailInDieOutbox).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_absender', function (Blueprint $table) {
            $table->id();

            $table->string('modul');       // „Core" oder Modulname
            $table->string('ausloeser');   // der Anlass, wie im Maillog

            $table->string('absender_name')->nullable();
            $table->string('absender_mail')->nullable();
            $table->string('antwort_an')->nullable();

            $table->timestamps();

            // Genau eine Konfig-Zeile je Kombination.
            $table->unique(['modul', 'ausloeser']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_absender');
    }
};
