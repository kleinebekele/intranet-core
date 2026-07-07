<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Umstellung auf Default-Deny: Menüpunkte OHNE Rollen sind ab jetzt nur noch
 * für Administratoren sichtbar/erreichbar. Damit sich BESTEHENDE Installationen
 * nicht ändern (dort galt "leer = alle"), bekommen vorhandene rollenlose
 * Punkte einmalig die Basis-Rolle `user` (die jeder Benutzer automatisch hat) —
 * außer sie waren ohnehin auf "nur Admins" gestellt (Punkt oder Modul).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('role_id', 'user')->exists()) {
            return; // Rolle fehlt (sehr frische Installation) → nichts zu erhalten
        }

        $items = DB::table('module_menu_items')
            ->join('modules', 'modules.id', '=', 'module_menu_items.module_id')
            ->where('module_menu_items.admins_only', false)
            ->where('modules.admins_only', false)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('module_menu_item_role')
                    ->whereColumn('module_menu_item_role.module_menu_item_id', 'module_menu_items.id');
            })
            ->pluck('module_menu_items.id');

        foreach ($items as $id) {
            DB::table('module_menu_item_role')->insert([
                'module_menu_item_id' => $id,
                'role_id' => 'user',
            ]);
        }
    }

    public function down(): void
    {
        // bewusst leer – das Entfernen der Rolle wäre ein Datenverlust
    }
};
