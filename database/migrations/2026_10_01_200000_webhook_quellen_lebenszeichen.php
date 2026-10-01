<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externer Wächter (2026-10-01): ein Windows-Skript im selben Netz fragt GET .../lebenszeichen ab und
 * schlägt per Mail Alarm, wenn das Intranet steht. lebenszeichen_am = letzte Abfrage (der Wächter
 * lebt), waechter_alarm_am = gemeldet, dass er schweigt (Gegenrichtung, Task Waechter/Lebenszeichen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekkon_webhook_quellen', function (Blueprint $table): void {
            if (! Schema::hasColumn('ekkon_webhook_quellen', 'lebenszeichen_am')) {
                $table->dateTime('lebenszeichen_am')->nullable();
            }
            if (! Schema::hasColumn('ekkon_webhook_quellen', 'waechter_alarm_am')) {
                $table->dateTime('waechter_alarm_am')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ekkon_webhook_quellen', function (Blueprint $table): void {
            $table->dropColumn(['lebenszeichen_am', 'waechter_alarm_am']);
        });
    }
};
