<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Token-Tabelle für Einladungslinks (Broker "einladungen").
 *
 * Getrennt von password_reset_tokens, weil Einladungen tagelang gültig sein
 * müssen, „Passwort vergessen" aber kurz bleiben soll – ein gemeinsamer Topf
 * würde einen der beiden Fristen aufweichen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('einladung_tokens')) {
            return;
        }

        Schema::create('einladung_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einladung_tokens');
    }
};
