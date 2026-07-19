<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt sich den Namen, den ein Import zuletzt gesetzt hat.
 *
 * Damit lässt sich unterscheiden, ob ein Benutzer seinen Namen selbst geändert
 * hat: Stimmt `name` noch mit `import_name` überein, ist er unberührt und eine
 * Namensänderung aus der Quelle (z. B. Linear: Heirat, Tippfehler) darf
 * nachziehen. Weichen sie ab, hat der Benutzer selbst Hand angelegt – dann
 * bleibt seine Fassung stehen.
 *
 * Nur importierende Module (aktuell der Linear-Benutzerabgleich) füllen das
 * Feld; für alle anderen bleibt es null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('import_name')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('import_name');
        });
    }
};
