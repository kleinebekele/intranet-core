<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('admins_only')->default(false)->after('is_enabled');
        });
        Schema::table('module_menu_items', function (Blueprint $table) {
            $table->boolean('admins_only')->default(false)->after('position');
        });

        // Bestehende "nur admin"-Sichtbarkeit (bisher über die admin-Rolle gelöst)
        // auf das neue Flag umziehen und die Rollen-Zuweisungen entfernen.
        $moduleIds = DB::table('module_role')->where('role_id', 'admin')->pluck('module_id')->all();
        if ($moduleIds) {
            DB::table('modules')->whereIn('id', $moduleIds)->update(['admins_only' => true]);
        }
        DB::table('module_role')->where('role_id', 'admin')->delete();

        $itemIds = DB::table('module_menu_item_role')->where('role_id', 'admin')->pluck('module_menu_item_id')->all();
        if ($itemIds) {
            DB::table('module_menu_items')->whereIn('id', $itemIds)->update(['admins_only' => true]);
        }
        DB::table('module_menu_item_role')->where('role_id', 'admin')->delete();
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('admins_only');
        });
        Schema::table('module_menu_items', function (Blueprint $table) {
            $table->dropColumn('admins_only');
        });
    }
};
