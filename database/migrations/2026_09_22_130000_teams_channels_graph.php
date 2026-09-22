<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweiter Weg nach Teams: direkt über Microsoft Graph statt über einen
 * Power-Automate-Workflow.
 *
 *  - ekkon_teams_channels.chat_id:    Chat- oder Kanal-ID (19:…@thread.v2 bzw.
 *                                     19:…@thread.tacv2). Gesetzt = Graph-Weg,
 *                                     die Webhook-URL darf dann fehlen.
 *  - ekkon_teams_channels.ablage_url: SharePoint-Ordner, in den Anhänge
 *                                     hochgeladen werden (Adresse aus dem Browser).
 *  - ekkon_graph_konten:              das Microsoft-Konto, in dessen Namen das
 *                                     Intranet postet (Refresh-Token verschlüsselt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ekkon_teams_channels', 'chat_id')) {
            Schema::table('ekkon_teams_channels', function (Blueprint $table): void {
                $table->text('webhook_url')->nullable()->change();
                $table->string('chat_id', 255)->nullable()->after('webhook_url');
                $table->string('ablage_url', 1000)->nullable()->after('chat_id');
            });
        }

        if (! Schema::hasTable('ekkon_graph_konten')) {
            Schema::create('ekkon_graph_konten', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ms_id', 64);
                $table->string('email', 255);
                $table->string('name', 255);
                $table->text('refresh_token');
                $table->text('scopes')->nullable();
                $table->timestamp('verbunden_am')->nullable();
                $table->timestamp('zuletzt_benutzt_am')->nullable();
                $table->string('letzter_fehler', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ekkon_graph_konten');

        if (Schema::hasColumn('ekkon_teams_channels', 'chat_id')) {
            Schema::table('ekkon_teams_channels', fn (Blueprint $t) => $t->dropColumn(['chat_id', 'ablage_url']));
        }
    }
};
