<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eltern-Kind-Beziehung zwischen Benutzern (Vormundschaft).
 *
 * Bewusst im CORE angesiedelt: die Beziehung ist grundlegend und wird von
 * mehreren Modulen genutzt (User-Import befüllt sie, die Schulkantine und
 * spätere Module lesen sie). Es ist eine n:m-Beziehung (ein Kind kann mehrere
 * Eltern haben, ein Elternteil mehrere Kinder) über eine Zwischentabelle:
 *
 *   user_id   = das Kind / Mündel
 *   parent_id = dessen Elternteil / Vormund
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users_parents', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['user_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_parents');
    }
};
