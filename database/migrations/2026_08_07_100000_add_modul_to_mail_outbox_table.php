<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweite Achse fürs Maillog: WOHER kommt die Mail?
 *
 * Bisher stand in `quelle` alles zusammen – mal der Klassenname der Mailable
 * („KundenNachricht"), mal ein vom Modul gesetzter Header. Das trennen wir:
 *  - `modul`  = wer hat sie ausgelöst: „Core" oder der Name des Moduls.
 *  - `quelle` = der ANLASS (Auslöser), z. B. „Zahlungserinnerung".
 *
 * Damit lässt sich der Absender je Modul+Auslöser eigenständig einstellen
 * (siehe `mail_absender`), und die Liste wird lesbar statt technisch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            // „Core" oder Modulname. Nullable, weil alte Zeilen keinen Wert
            // haben – die Anzeige zeigt für sie „Core".
            $table->string('modul')->nullable()->after('quelle');

            $table->index('modul');
        });
    }

    public function down(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->dropIndex(['modul']);
            $table->dropColumn('modul');
        });
    }
};
