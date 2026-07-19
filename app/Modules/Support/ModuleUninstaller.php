<?php

namespace App\Modules\Support;

use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Entfernt ein Modul aus dieser Instanz – die gemeinsame Grundlage für den
 * Befehl `modules:uninstall` und den Entfernen-Knopf in der Modul-Verwaltung.
 *
 * Zwei Stufen, bewusst getrennt:
 *  - {@see vorschau()} sagt, was am Modul hängt (nur lesend),
 *  - {@see entfernen()} räumt auf.
 *
 * Standard ist die schonende Variante: Nur die Registrierung verschwindet
 * (Modul, Menüpunkte samt Rollen, sprechende Adressen). Die Tabellen des
 * Moduls bleiben stehen, bis jemand ausdrücklich `$mitDaten` verlangt.
 */
class ModuleUninstaller
{
    public function __construct(private ModuleRegistry $registry) {}

    /**
     * Alles, was vor einer Entscheidung sichtbar sein sollte – inklusive der
     * Tabellen des Moduls und ihrer Zeilenzahlen.
     *
     * @return array<string, mixed>|null null, wenn es weder Registrierung noch Paket gibt
     */
    public function vorschau(string $key): ?array
    {
        $module = Module::with('menuItems.roles')->where('key', $key)->first();
        $manifest = $this->registry->manifest($key);

        if (! $module && ! $manifest) {
            return null;
        }

        return [
            'key' => $key,
            'name' => $module?->name ?? $manifest?->name ?? $key,
            'modul' => $module,
            'paket_installiert' => (bool) $manifest,
            'paket_name' => $manifest ? $this->paketName($manifest) : null,
            'menuepunkte' => $module?->menuItems ?? collect(),
            'adressen' => $this->routenEinstellungen($key)->count(),
            'migrationen' => $manifest ? $this->migrationenMitTabellen($manifest) : [],
        ];
    }

    /**
     * Führt die Deinstallation aus.
     *
     * @return array<string, mixed> Bericht über das Aufgeräumte
     *
     * @throws RuntimeException wenn Tabellen gelöscht werden sollen, das Paket
     *                          aber nicht mehr installiert ist – dann fehlen die
     *                          Migrationsdateien und ein Zurückrollen ist unmöglich.
     */
    public function entfernen(string $key, bool $mitDaten = false): array
    {
        $vorschau = $this->vorschau($key);

        if (! $vorschau) {
            throw new RuntimeException("Kein Modul mit dem Schlüssel „{$key}\" gefunden.");
        }

        if ($mitDaten && ! $vorschau['paket_installiert']) {
            throw new RuntimeException(
                "Das Paket zu „{$key}\" ist nicht mehr installiert – seine Migrationen können ".
                'nicht zurückgerollt werden. Dafür das Paket kurz erneut einbinden.'
            );
        }

        // Erst die Migrationen: Schlägt das fehl, steht die Registrierung noch
        // und der Vorgang lässt sich unverändert wiederholen.
        $zurueckgerollt = $mitDaten
            ? $this->migrationenZurueckrollen($this->registry->manifest($key))
            : [];

        $adressen = $this->routenEinstellungen($key)->delete();

        $punkte = 0;
        if ($modul = $vorschau['modul']) {
            // module_menu_items und die Rollen-Zuordnungen hängen per
            // Fremdschlüssel-Kaskade daran und gehen mit.
            $punkte = $modul->menuItems->count();
            $modul->delete();
        }

        return [
            'name' => $vorschau['name'],
            'paket_name' => $vorschau['paket_name'],
            'menuepunkte' => $punkte,
            'adressen' => $adressen,
            'migrationen' => $zurueckgerollt,
        ];
    }

    /**
     * Die gelaufenen Migrationen des Moduls, je mit den Tabellen, die sie
     * anlegen, und deren aktueller Zeilenzahl.
     *
     * @return array<int, array{name: string, datei: string, tabellen: array<int, array{name: string, vorhanden: bool, zeilen: int}>}>
     */
    private function migrationenMitTabellen(ModuleManifest $manifest): array
    {
        return array_map(function (string $datei): array {
            $tabellen = array_map(fn (string $tabelle): array => [
                'name' => $tabelle,
                'vorhanden' => $vorhanden = Schema::hasTable($tabelle),
                'zeilen' => $vorhanden ? DB::table($tabelle)->count() : 0,
            ], $this->tabellenAus($datei));

            return [
                'name' => basename($datei, '.php'),
                'datei' => $datei,
                'tabellen' => $tabellen,
            ];
        }, $this->gelaufeneMigrationen($manifest));
    }

    /**
     * Migrationsdateien des Moduls, die laut `migrations`-Tabelle gelaufen sind.
     *
     * @return string[]
     */
    private function gelaufeneMigrationen(ModuleManifest $manifest): array
    {
        $gelaufen = DB::table('migrations')->pluck('migration')->all();

        return array_values(array_filter(
            $manifest->migrationFiles(),
            fn (string $datei) => in_array(basename($datei, '.php'), $gelaufen, true),
        ));
    }

    /**
     * Welche Tabellen legt diese Migration an? Aus `Schema::create('x', …)`
     * gelesen – rein für die Anzeige vor der Entscheidung. Findet die Suche
     * nichts, wird eben nur der Dateiname genannt.
     *
     * @return string[]
     */
    private function tabellenAus(string $datei): array
    {
        $inhalt = @file_get_contents($datei) ?: '';

        preg_match_all('/Schema::create\(\s*[\'"]([^\'"]+)[\'"]/', $inhalt, $treffer);

        return array_values(array_unique($treffer[1] ?? []));
    }

    /**
     * Rollt die Migrationen des Moduls zurück – neueste zuerst, so wie Laravel
     * es täte, aber gezielt nur die dieses Moduls.
     *
     * `migrate:rollback --path=…` kommt dafür nicht in Frage: Es rollt immer
     * den letzten Stapel zurück, und der enthält typischerweise Migrationen
     * ganz anderer Pakete.
     *
     * @return string[] Namen der zurückgerollten Migrationen
     */
    private function migrationenZurueckrollen(ModuleManifest $manifest): array
    {
        $erledigt = [];

        foreach (array_reverse($this->gelaufeneMigrationen($manifest)) as $datei) {
            // Modul-Migrationen sind anonyme Klassen: Die Datei liefert das
            // fertige Objekt zurück (nichts anderes macht Laravels Migrator).
            $migration = require $datei;

            if (! is_object($migration) || ! method_exists($migration, 'down')) {
                continue;
            }

            $migration->down();

            $name = basename($datei, '.php');
            DB::table('migrations')->where('migration', $name)->delete();
            $erledigt[] = $name;
        }

        return $erledigt;
    }

    /**
     * Alle Routen-Einstellungen, deren Route zu diesem Modul gehört.
     *
     * Steht der Code schon auf dem Server, die Migration aber noch nicht
     * (der Moment zwischen `git pull` und `migrate`), fehlt die Tabelle –
     * dann darf die Modul-Verwaltung nicht mit einem 500er aussteigen.
     */
    private function routenEinstellungen(string $key)
    {
        if (! Schema::hasTable('route_settings')) {
            return DB::table('migrations')->whereRaw('1 = 0');
        }

        return DB::table('route_settings')
            ->where('route_name', "module.{$key}")
            ->orWhere('route_name', 'like', "module.{$key}.%");
    }

    /** Paketname aus der composer.json des Moduls – für den Schlusshinweis. */
    private function paketName(ModuleManifest $manifest): ?string
    {
        if (! $manifest->basePath || ! is_file($datei = "{$manifest->basePath}/composer.json")) {
            return null;
        }

        $daten = json_decode((string) file_get_contents($datei), true);

        return is_array($daten) ? ($daten['name'] ?? null) : null;
    }
}
