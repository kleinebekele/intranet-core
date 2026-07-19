<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merkt sich die E-Mail, die ein Import zuletzt gesetzt hat.
 *
 * Gegenstück zu `import_name`: Stimmt `email` noch mit `import_email` überein,
 * hat der Benutzer sie nicht selbst geändert – dann darf eine Korrektur aus der
 * Quelle (z. B. Linear) nachziehen. Weicht sie ab, gehört die Adresse dem
 * Benutzer (im Profil gesetzt) und bleibt.
 *
 * Nur importierende Module füllen das Feld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('import_email')->nullable()->after('import_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('import_email');
        });
    }
};
