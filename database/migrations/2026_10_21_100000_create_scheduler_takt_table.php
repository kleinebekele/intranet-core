<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Takt des Laravel-Schedulers: eine Zeile je Minute, in der Cron `schedule:run`
 * gestartet hat (App\Support\SchedulerTakt). Die eindeutige Minute ist zugleich
 * die Sperre gegen einen zweiten Start in derselben Minute – Cron holt eine
 * verpasste Minute nach und startet dann doppelt (RAV-Server, 2026-10-21).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduler_takt')) {
            return;
        }

        Schema::create('scheduler_takt', function (Blueprint $table): void {
            $table->string('minute', 16)->primary(); // Y-m-d H:i
            $table->dateTime('gestartet_am');
            $table->unsignedTinyInteger('sekunde'); // Startsekunde in der Minute – wandert sie, hinkt Cron
            $table->unsignedInteger('doppelt')->default(0); // weitere Starts derselben Minute, abgewiesen
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_takt');
    }
};
