<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine freie Referenz je Ausgangskorb-Zeile.
 *
 * Damit kann ein Modul seine verschickten Mails im Maillog gezielt wiederfinden
 * – etwa der Newsletter, der auf der Ausgaben-Seite den echten Zustellstatus je
 * Empfänger zeigen will (`newsletter:<ausgabe>:<empfänger>`). Der Core selbst
 * wertet die Referenz nicht aus; sie wird nur beim Einliefern aus dem internen
 * Header übernommen (siehe MailInDieOutbox).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->string('referenz')->nullable()->after('quelle')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->dropColumn('referenz');
        });
    }
};
