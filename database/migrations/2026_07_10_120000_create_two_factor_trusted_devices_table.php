<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Dieses Gerät merken" für die 2FA-Abfrage. Bewusst eine eigene Tabelle
        // statt des App-Caches: so überlebt ein vertrautes Gerät `cache:clear`
        // bzw. `optimize:clear` und damit jeden Deploy.
        Schema::create('two_factor_trusted_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64); // sha256 des Zufalls-Tokens (nie im Klartext)
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_trusted_devices');
    }
};
