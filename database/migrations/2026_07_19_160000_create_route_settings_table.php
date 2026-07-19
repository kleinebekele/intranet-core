<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprechende Adressen und feste Titel je Route.
 *
 * Der Pfad ersetzt die ursprüngliche Adresse vollständig: Nach dem Setzen zeigen
 * auch alle Menüpunkte und internen Verweise dorthin, weil die Route selbst
 * umgeschrieben wird (siehe \App\Support\RoutenAliase). Die alte Adresse bleibt
 * als Weiterleitung bestehen, damit verschickte Links und Lesezeichen nicht
 * ins Leere laufen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_settings', function (Blueprint $table) {
            // Der Routen-Name ist der Schlüssel: Er überlebt Umbenennungen der
            // URL und ist das, woran Laravel Verweise festmacht.
            $table->string('route_name')->primary();

            // Ohne führenden Schrägstrich, z. B. "speiseplan".
            $table->string('pfad')->nullable()->unique();

            // Überschreibt den Seitenteil im Browser-Titel.
            $table->string('titel')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_settings');
    }
};
