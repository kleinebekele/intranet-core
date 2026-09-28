<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wartezeit zwischen zwei Zustellversuchen.
 *
 * Bisher wurde eine gescheiterte Mail jede Minute erneut probiert – bei einer
 * vorübergehenden Sperre des Providers (421 „too many connections") waren die
 * Versuche nach drei Minuten verbraucht, obwohl die Sperre noch lief. Jetzt
 * wartet die Mail bis `naechster_versuch_am`; NULL = sofort.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mail_outbox', 'naechster_versuch_am')) {
            return;
        }

        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->timestamp('naechster_versuch_am')->nullable()->after('versuche');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mail_outbox', 'naechster_versuch_am')) {
            return;
        }

        Schema::table('mail_outbox', function (Blueprint $table) {
            $table->dropColumn('naechster_versuch_am');
        });
    }
};
