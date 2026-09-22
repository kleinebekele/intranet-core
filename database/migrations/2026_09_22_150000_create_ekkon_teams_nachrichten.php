<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams-Chat-Eingang: Nachrichten, die andere dem verbundenen (Bot-)Konto in
 * Teams schreiben. Der Lauscher (`teams:lauschen`) holt sie per Graph ab.
 *
 *  - ekkon_teams_nachrichten: eine Zeile je Nachricht (Fremdnachrichten, keine
 *    eigenen), roh mit HTML und Klartext; `verarbeitet_am`/`antwort` füllt die
 *    spätere Fachlogik (KI).
 *  - ekkon_teams_chat_stand: je Chat, bis wann gelesen wurde – so holt der
 *    Lauscher nach einem Neustart nicht alles noch einmal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ekkon_teams_nachrichten')) {
            Schema::create('ekkon_teams_nachrichten', function (Blueprint $table): void {
                $table->id();
                $table->string('chat_id', 255)->index();
                $table->string('chat_titel', 255)->nullable();
                $table->string('chat_typ', 32)->nullable();
                $table->string('nachricht_id', 64)->unique();
                $table->string('von_id', 64)->nullable();
                $table->string('von_name', 255)->nullable();
                $table->longText('text')->nullable();
                $table->longText('html')->nullable();
                $table->json('anhaenge')->nullable();
                $table->timestamp('gesendet_am')->nullable()->index();
                $table->timestamp('verarbeitet_am')->nullable();
                $table->string('verarbeitung', 255)->nullable();
                $table->longText('antwort')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ekkon_teams_chat_stand')) {
            Schema::create('ekkon_teams_chat_stand', function (Blueprint $table): void {
                $table->string('chat_id', 255)->primary();
                $table->string('titel', 255)->nullable();
                $table->timestamp('zuletzt_gesehen_am')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ekkon_teams_chat_stand');
        Schema::dropIfExists('ekkon_teams_nachrichten');
    }
};
