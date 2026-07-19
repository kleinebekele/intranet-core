<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benutzer sperren, ohne sie zu löschen.
 *
 * Wer die Schule verlässt, soll sich nicht mehr anmelden können – seine Spuren
 * (Bestellungen, Zeugnisse, Protokolleinträge) müssen aber erhalten bleiben.
 * Löschen wäre dafür das falsche Werkzeug.
 *
 * `gesperrt_am` ist bewusst ein Zeitpunkt und kein Ja/Nein: Man sieht dadurch
 * ohne Zusatzaufwand, seit wann die Sperre gilt. `gesperrt_grund` steht dabei,
 * damit später nachvollziehbar ist, warum – vor allem bei automatisch
 * gesperrten Konten aus dem Linear-Abgleich.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('gesperrt_am')->nullable()->after('source');
            $table->string('gesperrt_grund')->nullable()->after('gesperrt_am');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gesperrt_am', 'gesperrt_grund']);
        });
    }
};
