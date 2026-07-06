<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_menu_items', function (Blueprint $table) {
            // Icon-Name pro Menüpunkt (siehe x-module-icon). Null = neutraler Punkt.
            $table->string('icon')->nullable()->after('route_name');
        });
    }

    public function down(): void
    {
        Schema::table('module_menu_items', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
