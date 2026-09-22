<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ekkon_teams_nachrichten.diagnose: was die KI zur Antwort bekam (erkannter
 * Absender mit Rollen, Wissenstreffer, Fehler der Wissensquellen) – als JSON
 * fürs Modal, damit die Verarbeitungsspalte kurz bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ekkon_teams_nachrichten', 'diagnose')) {
            Schema::table('ekkon_teams_nachrichten', function (Blueprint $table): void {
                $table->json('diagnose')->nullable()->after('antwort');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ekkon_teams_nachrichten', 'diagnose')) {
            Schema::table('ekkon_teams_nachrichten', fn (Blueprint $t) => $t->dropColumn('diagnose'));
        }
    }
};
