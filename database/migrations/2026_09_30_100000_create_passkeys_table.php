<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passkeys (WebAuthn): Anmeldung per Face ID, Touch ID, Windows Hello oder
 * Sicherheitsschlüssel. Je Gerät/Schlüsselbund eine Zeile; gespeichert wird
 * nur der öffentliche Schlüssel – das Geheimnis verlässt das Gerät nie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('passkeys')) {
            return;
        }

        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // Base64url der Credential-ID. Üblich sind 16–64 Byte; 512 Zeichen
            // lassen reichlich Luft und passen noch in einen MySQL-Unique-Index.
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('aaguid', 36)->nullable();
            $table->timestamp('zuletzt_benutzt_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
