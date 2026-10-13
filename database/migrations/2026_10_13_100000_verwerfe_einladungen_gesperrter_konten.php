<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wartende Einladungen schon gesperrter Konten verwerfen (z. B. doppelt angelegte
 * Konten). Ab jetzt erledigt das User::sperren() selbst.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('einladungen')) {
            return;
        }

        DB::table('einladungen')
            ->where('status', 'wartend')
            ->whereIn('user_id', DB::table('users')->whereNotNull('gesperrt_am')->select('id'))
            ->update(['status' => 'verworfen', 'entschieden_am' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Bewusst nichts: welche Einladungen vorher wartend waren, ist nicht mehr bekannt.
    }
};
