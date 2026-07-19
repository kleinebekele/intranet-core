<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einladungen warten hier, bis ein Mensch sie freigibt.
 *
 * Ein Import legt schnell hunderte Benutzer an. Ginge dabei automatisch je eine
 * Einladung raus, hätte man mit einem Klick hunderte Mails verschickt – und
 * verschickte Mails holt man nicht zurück. Deshalb schreibt der Import nur eine
 * Absichtserklärung in diese Tabelle; verschickt wird erst nach ausdrücklicher
 * Freigabe in der Verwaltung.
 *
 * Bewusst EINE Zeile je Benutzer (`user_id` eindeutig): Läuft der Import
 * mehrfach, soll daraus keine Warteschlange von Dubletten entstehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('einladungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // wartend | verschickt | verworfen | unzustellbar
            $table->string('status', 20)->default('wartend');

            // Wer die Einladung angefordert hat, z. B. "Linear/BenutzerImport".
            $table->string('quelle')->nullable();

            $table->timestamp('entschieden_am')->nullable();
            $table->foreignId('entschieden_von')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('einladungen');
    }
};
