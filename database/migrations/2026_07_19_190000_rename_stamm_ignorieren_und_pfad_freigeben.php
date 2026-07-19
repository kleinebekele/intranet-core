<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Nachwehen der neuen Bedeutung des Feldes bei Unterseiten.
 *
 * 1. Der Eintrag einer Unterseite ist jetzt RELATIV zu ihrem Bereich. Das
 *    Häkchen heißt deshalb nicht mehr „Stammpfad ignorieren", sondern sagt, was
 *    es tut: Der Pfad ist absolut.
 *
 * 2. Die Eindeutigkeit auf `pfad` muss weg. Sie galt für vollständige Adressen –
 *    bei relativen Angaben ist `benachrichtigungen` unter zwei verschiedenen
 *    Bereichen aber völlig in Ordnung und ergibt trotzdem zwei verschiedene
 *    Adressen. Kollisionen prüft weiterhin der SeoController, und zwar dort,
 *    wo sie entstehen: an der fertigen Adresse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_settings', function (Blueprint $table): void {
            $table->renameColumn('stamm_ignorieren', 'absoluter_pfad');
        });

        Schema::table('route_settings', function (Blueprint $table): void {
            $table->dropUnique(['pfad']);
        });
    }

    public function down(): void
    {
        Schema::table('route_settings', function (Blueprint $table): void {
            $table->renameColumn('absoluter_pfad', 'stamm_ignorieren');
            $table->unique('pfad');
        });
    }
};
