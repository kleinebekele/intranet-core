<?php

namespace App\Modules\Support;

use Illuminate\Support\Facades\DB;

/**
 * Was gehört migrationsseitig zu einem Modul?
 *
 * Solange das Paket installiert ist, steht die Antwort in seinem Verzeichnis.
 * Danach nicht mehr – deshalb schreibt {@see aufzeichnen()} sie bei jedem
 * `modules:sync` in die Tabelle `module_migrations` mit. Nur so lässt sich ein
 * Modul auch dann noch vollständig entfernen, wenn sein Paket längst weg ist.
 */
class ModuleMigrations
{
    /** Merkt sich die Migrationen eines installierten Moduls. */
    public function aufzeichnen(ModuleManifest $manifest): void
    {
        $bekannt = [];

        foreach ($manifest->migrationFiles() as $datei) {
            $name = basename($datei, '.php');
            $bekannt[] = $name;

            DB::table('module_migrations')->updateOrInsert(
                ['module_key' => $manifest->key, 'migration' => $name],
                ['tabellen' => json_encode($this->tabellenAus($datei)), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        // Migrationen, die das Modul nicht mehr mitbringt, aus dem Gedächtnis
        // streichen – sonst würden umbenannte Dateien doppelt geführt.
        DB::table('module_migrations')
            ->where('module_key', $manifest->key)
            ->whereNotIn('migration', $bekannt ?: ['__keine__'])
            ->delete();
    }

    /**
     * Die Migrationen des Moduls, die laut `migrations`-Tabelle gelaufen sind.
     *
     * Ist das Paket noch da, gilt sein Verzeichnis (dort steht auch das
     * `down()`, mit dem sich sauber zurückrollen lässt). Sonst das Gedächtnis –
     * dann bleibt nur, die aufgezeichneten Tabellen direkt zu verwerfen.
     *
     * @return array<int, array{name: string, datei: string|null, tabellen: string[]}>
     */
    public function gelaufene(string $key, ?ModuleManifest $manifest): array
    {
        $gelaufen = DB::table('migrations')->pluck('migration')->all();

        if ($manifest) {
            $treffer = [];

            foreach ($manifest->migrationFiles() as $datei) {
                $name = basename($datei, '.php');

                if (in_array($name, $gelaufen, true)) {
                    $treffer[] = ['name' => $name, 'datei' => $datei, 'tabellen' => $this->tabellenAus($datei)];
                }
            }

            return $treffer;
        }

        return DB::table('module_migrations')
            ->where('module_key', $key)
            ->whereIn('migration', $gelaufen)
            ->orderBy('migration')
            ->get()
            ->map(fn ($zeile) => [
                'name' => $zeile->migration,
                'datei' => null,
                'tabellen' => json_decode($zeile->tabellen, true) ?: [],
            ])
            ->all();
    }

    /** Aufzeichnung eines entfernten Moduls wegwerfen. */
    public function vergessen(string $key): void
    {
        DB::table('module_migrations')->where('module_key', $key)->delete();
    }

    /**
     * Welche Tabellen legt diese Migration an? Aus `Schema::create('x', …)`
     * gelesen. Leer bei Migrationen, die nur bestehende Tabellen ändern – die
     * lassen sich ohne ihr `down()` auch nicht rückgängig machen.
     *
     * @return string[]
     */
    public function tabellenAus(string $datei): array
    {
        $inhalt = @file_get_contents($datei) ?: '';

        preg_match_all('/Schema::create\(\s*[\'"]([^\'"]+)[\'"]/', $inhalt, $treffer);

        return array_values(array_unique($treffer[1] ?? []));
    }
}
