<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selbst geänderte Anmelde-Adresse wartet auf Bestätigung.
 *
 * Wer im Profil eine neue E-Mail einträgt, bekommt einen Link an genau diese
 * Adresse. Erst der Klick darauf übernimmt sie nach `email` – bis dahin steht
 * sie hier, der Link-Schlüssel nur als SHA-256-Hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_neu')) {
                $table->string('email_neu')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'email_neu_token')) {
                $table->string('email_neu_token', 64)->nullable()->after('email_neu');
            }
            if (! Schema::hasColumn('users', 'email_neu_am')) {
                $table->timestamp('email_neu_am')->nullable()->after('email_neu_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_neu', 'email_neu_token', 'email_neu_am']);
        });
    }
};
