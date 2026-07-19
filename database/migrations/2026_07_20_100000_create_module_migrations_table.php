<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gedächtnis des Cores: Welche Migration – und damit welche Tabelle – gehört
 * zu welchem Modul?
 *
 * Gefüllt wird das bei jedem `modules:sync`, also solange das Paket noch da
 * ist. Genau darum geht es: Beim Deinstallieren ist das Paket oft schon weg,
 * seine Migrationsdateien mit ihm. Ohne diese Aufzeichnung wüsste dann
 * niemand mehr, dass `user_imports` einmal zu `userimport` gehörte, und die
 * Tabelle bliebe für immer verwaist stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_migrations', function (Blueprint $table) {
            // Bewusst der Modul-SCHLÜSSEL, keine Fremdschlüssel-Beziehung:
            // Der Eintrag muss den Modul-Datensatz überleben können.
            $table->string('module_key');
            $table->string('migration');

            // Die Tabellen, die diese Migration anlegt (aus Schema::create
            // gelesen). Leer, wenn sie nur bestehende Tabellen ändert.
            $table->json('tabellen');

            $table->timestamps();

            $table->primary(['module_key', 'migration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_migrations');
    }
};
