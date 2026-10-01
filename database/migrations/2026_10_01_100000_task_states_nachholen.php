<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verschobene Task-Läufe nachholen (2026-10-01): Ein Task, dessen Vorbedingung fehlt (Wawi nicht
 * erreichbar, Vorgänger noch nicht durch), wird nicht gestartet, sondern ab nachholen_ab erneut
 * versucht - auch außerhalb seiner Cron-Zeit (Befehl ekkon:nachholen, jede Minute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekkon_task_states', function (Blueprint $table): void {
            if (! Schema::hasColumn('ekkon_task_states', 'nachholen_ab')) {
                $table->dateTime('nachholen_ab')->nullable();
            }
            if (! Schema::hasColumn('ekkon_task_states', 'nachholen_seit')) {
                $table->dateTime('nachholen_seit')->nullable();
            }
            if (! Schema::hasColumn('ekkon_task_states', 'nachholen_grund')) {
                $table->string('nachholen_grund', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ekkon_task_states', function (Blueprint $table): void {
            $table->dropColumn(['nachholen_ab', 'nachholen_seit', 'nachholen_grund']);
        });
    }
};
