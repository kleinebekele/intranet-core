<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ekkon_teams_nachrichten.bot_erwaehnt: In Gruppen- und Besprechungschats soll
 * der Bot nur antworten, wenn er mit @ angesprochen wurde – sonst redet er in
 * jedes Gespräch hinein. Der Lauscher liest das aus den `mentions` der Nachricht.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ekkon_teams_nachrichten', 'bot_erwaehnt')) {
            Schema::table('ekkon_teams_nachrichten', function (Blueprint $table): void {
                $table->boolean('bot_erwaehnt')->default(false)->after('anhaenge');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ekkon_teams_nachrichten', 'bot_erwaehnt')) {
            Schema::table('ekkon_teams_nachrichten', fn (Blueprint $t) => $t->dropColumn('bot_erwaehnt'));
        }
    }
};
