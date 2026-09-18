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

        // Umzug zwischen Modulen: Wer die Datei mitbringt, dem gehört sie. Zieht
        // eine Migration (gleicher Dateiname) von Modul A nach B, verliert A hier
        // seinen Vermerk – sonst würde ein späteres Entfernen von A die Tabellen
        // löschen, die längst B gehören.
        if ($bekannt !== []) {
            DB::table('module_migrations')
                ->where('module_key', '!=', $manifest->key)
                ->whereIn('migration', $bekannt)
                ->delete();
        }
    }

    /**
     * Beansprucht ein ANDERES installiertes Modul diese Migration (bringt eine
     * Datei gleichen Namens mit)? Dann darf ein Entfernen von `$key` sie nicht
     * zurückrollen – auch nicht, solange beide Pakete noch nebeneinander
     * installiert sind und der Vermerk noch nicht umgeschrieben wurde.
     */
    private function vonAnderemBeansprucht(string $key, string $migration): bool
    {
        // Der Core selbst zählt auch: Ekkon zog 2026-09 samt Migrationen hierher.
        if (is_file(database_path("migrations/{$migration}.php"))) {
            return true;
        }

        foreach (app(ModuleRegistry::class)->manifests() as $anderes) {
            if ($anderes->key === $key) {
                continue;
            }

            foreach ($anderes->migrationFiles() as $datei) {
                if (basename($datei, '.php') === $migration) {
                    return true;
                }
            }
        }

        return false;
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

                if (in_array($name, $gelaufen, true) && ! $this->vonAnderemBeansprucht($key, $name)) {
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
            ->reject(fn ($zeile) => $this->vonAnderemBeansprucht($key, $zeile->migration))
            ->values()
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
