<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einstellungen, die Administratoren zur Laufzeit ändern können – im Gegensatz
 * zur `.env`, die nur beim Deploy angefasst wird.
 *
 * Bewusst ein schlichter Schlüssel/Wert-Speicher: Es sind wenige, sehr
 * unterschiedliche Werte (Titel, Favicon-Pfad, Stundenlimit). Eine Spalte je
 * Einstellung würde für jede neue Kleinigkeit eine Migration erzwingen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('schluessel')->primary();
            $table->text('wert')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
