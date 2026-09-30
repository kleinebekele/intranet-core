<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Später" beim Passkey-Angebot: eine Woche Ruhe. Bis wann, steht hier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'passkey_angebot_pause_bis')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('passkey_angebot_pause_bis')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'passkey_angebot_pause_bis')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passkey_angebot_pause_bis');
        });
    }
};
