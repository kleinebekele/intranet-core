<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausnahme vom Stammpfad.
 *
 * Normalfall ist das Erben: Wer der Übersicht eine Adresse gibt, meint den
 * ganzen Bereich. Gelegentlich soll eine Unterseite aber bewusst NICHT
 * mitwandern – dafür dieses Häkchen. Standard ist deshalb `false`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_settings', function (Blueprint $table): void {
            $table->boolean('stamm_ignorieren')->default(false)->after('pfad');
        });
    }

    public function down(): void
    {
        Schema::table('route_settings', function (Blueprint $table): void {
            $table->dropColumn('stamm_ignorieren');
        });
    }
};
