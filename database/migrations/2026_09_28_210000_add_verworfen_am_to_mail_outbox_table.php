<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neuer Status „verworfen" im Ausgangskorb: gescheiterte Mails, um die sich
 * niemand mehr kümmern muss – von Hand oder automatisch nach 10 Tagen. Der
 * Zeitpunkt steht in `verworfen_am`; `mail:aufraeumen` entfernt sie später.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mail_outbox', 'verworfen_am')) {
            return;
        }

        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->timestamp('verworfen_am')->nullable()->after('versendet_am');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mail_outbox', 'verworfen_am')) {
            return;
        }

        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->dropColumn('verworfen_am');
        });
    }
};
