<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links im Footer (Systemeinstellungen → Footer): extern (Adresse) oder intern
 * (eigene Seite unter /seite/{slug} mit HTML-Inhalt und optionaler Einbindung,
 * z. B. Datenschutzerklärung eines Dienstleisters per Skript).
 *
 * Legt Impressum und Datenschutz als Vorlage mit Platzhaltertext an.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('footer_links')) {
            Schema::create('footer_links', function (Blueprint $table): void {
                $table->id();
                $table->string('titel', 100);
                $table->string('art', 10); // intern | extern
                $table->string('url', 2000)->nullable();
                $table->string('slug', 100)->nullable()->unique();
                $table->longText('inhalt')->nullable();
                $table->text('einbindung')->nullable();
                $table->unsignedInteger('position')->default(0);
                $table->boolean('aktiv')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('footer_links')->exists()) {
            return;
        }

        $lorem = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '
            .'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>'
            .'<p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. '
            .'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>';

        DB::table('footer_links')->insert([
            [
                'titel' => 'Impressum', 'art' => 'intern', 'url' => null, 'slug' => 'impressum',
                'inhalt' => '<h2>Impressum</h2><h3>Angaben gemäß § 5 DDG</h3>'.$lorem,
                'einbindung' => null, 'position' => 1, 'aktiv' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'titel' => 'Datenschutz', 'art' => 'intern', 'url' => null, 'slug' => 'datenschutz',
                'inhalt' => '<h2>Datenschutzerklärung</h2><h3>1. Verantwortliche Stelle</h3>'.$lorem.'<h3>2. Erhobene Daten</h3>'.$lorem,
                'einbindung' => null, 'position' => 2, 'aktiv' => true, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_links');
    }
};
