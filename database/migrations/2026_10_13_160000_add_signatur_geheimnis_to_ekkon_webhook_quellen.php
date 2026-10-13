<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionale Signatur je Webhook-Quelle. Ist ein Geheimnis gesetzt, nimmt der
 * Eingang nur noch Aufrufe mit gültiger HMAC-Signatur und frischem Zeitstempel
 * an – dann hilft eine abgeschriebene URL allein nicht mehr. Verschlüsselt
 * gespeichert (Cast `encrypted`), daher `text`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ekkon_webhook_quellen', 'signatur_geheimnis')) {
            Schema::table('ekkon_webhook_quellen', function (Blueprint $table): void {
                $table->text('signatur_geheimnis')->nullable()->after('schluessel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ekkon_webhook_quellen', 'signatur_geheimnis')) {
            Schema::table('ekkon_webhook_quellen', function (Blueprint $table): void {
                $table->dropColumn('signatur_geheimnis');
            });
        }
    }
};
