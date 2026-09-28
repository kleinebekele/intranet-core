<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zugriffsstufe je Menüpunkt und Rolle: lesen < bearbeiten < verwalten
 * (siehe App\Modules\Support\Zugriffsstufe).
 *
 * Bestehende Zuordnungen werden `verwalten` – bisher durfte, wer eine Seite
 * sehen konnte, dort auch alles. Live ändert sich dadurch nichts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('module_menu_item_role', 'stufe')) {
            Schema::table('module_menu_item_role', function (Blueprint $table) {
                $table->string('stufe', 20)->default('verwalten');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('module_menu_item_role', 'stufe')) {
            Schema::table('module_menu_item_role', function (Blueprint $table) {
                $table->dropColumn('stufe');
            });
        }
    }
};
