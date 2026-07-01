<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('key');            // unique within the module
            $table->string('label');          // text shown in the menu
            $table->string('route_name');     // Laravel route name to link to
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['module_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_menu_items');
    }
};
