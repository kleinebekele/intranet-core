<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearbeitbare Mailvorlagen.
 *
 * Gespeichert wird nur, was jemand geändert hat: Ohne Zeile gilt der im Code
 * mitgelieferte Standard. Dadurch verbessert eine neue Core-Version die Texte
 * für alle, die nie etwas angepasst haben – und wer angepasst hat, behält seine
 * Fassung.
 *
 * Beide Fassungen werden gepflegt und verschickt (multipart/alternative). Der
 * Textteil ist kein Zierrat: Manche Programme zeigen ihn, und reine HTML-Mails
 * gelten Spamfiltern als verdächtig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_vorlagen', function (Blueprint $table) {
            // z. B. "einladung", "passwort_reset" oder "_rahmen" für das Layout.
            $table->string('schluessel')->primary();

            $table->string('betreff')->nullable();
            $table->longText('html')->nullable();
            $table->longText('text')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_vorlagen');
    }
};
