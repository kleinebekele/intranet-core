<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optische Gruppe eines Menüpunkts: Module mit vielen Unterseiten können
 * verwandte Punkte unter einer aufklappbaren Überschrift bündeln.
 *
 * Bewusst nur ein Etikett am Punkt selbst – keine Eltern-Einträge, keine
 * Verschachtelung in der Datenbank. Jeder Menüpunkt bleibt ein eigener
 * Eintrag mit eigenen Rollen, die Zugriffssteuerung ändert sich dadurch
 * nicht; die Gruppe existiert allein in der Darstellung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_menu_items', function (Blueprint $table) {
            $table->string('group_label')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('module_menu_items', function (Blueprint $table) {
            $table->dropColumn('group_label');
        });
    }
};
