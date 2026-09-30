<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nach der Passwort-Anmeldung bietet das Intranet an, einen Passkey anzulegen.
 * Wer "Nicht mehr fragen" wählt, bekommt hier den Zeitpunkt – das Anlegen im
 * Profil bleibt trotzdem möglich.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'passkey_angebot_aus_am')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('passkey_angebot_aus_am')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'passkey_angebot_aus_am')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passkey_angebot_aus_am');
        });
    }
};
