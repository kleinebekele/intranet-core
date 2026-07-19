<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vorlage-Migration eines fiktiven Moduls – nur für die Tests von modules:uninstall. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tm_dinge', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_dinge');
    }
};
