<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ekkon_notifications.anhang_pfad / anhang_name: optionale Datei zu einer
 * Meldung (z. B. Meeting-Zusammenfassung als Word-Dokument). Die Datei liegt
 * auf der Disk `local` unter ekkon/anhaenge/…; mehrere Zielzeilen derselben
 * Meldung teilen sich eine Datei. Mail hängt sie an, Teams bekommt sie
 * Base64-kodiert im Webhook-Umschlag (der Workflow legt sie im Kanal ab).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ekkon_notifications', 'anhang_pfad')) {
            Schema::table('ekkon_notifications', function (Blueprint $table): void {
                $table->string('anhang_pfad', 255)->nullable()->after('html');
                $table->string('anhang_name', 255)->nullable()->after('anhang_pfad');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ekkon_notifications', 'anhang_pfad')) {
            Schema::table('ekkon_notifications', fn (Blueprint $t) => $t->dropColumn(['anhang_pfad', 'anhang_name']));
        }
    }
};
