<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Das Gegenstück zu `modules:sync`: entfernt ein Modul sauber aus der Instanz.
 *
 *   php artisan modules:uninstall <key>              nur die Registrierung
 *   php artisan modules:uninstall <key> --mit-daten  zusätzlich Tabellen zurückrollen
 *   php artisan modules:uninstall <key> --dry-run    nur anzeigen, nichts tun
 *
 * ⚠️ REIHENFOLGE: erst dieser Befehl, DANN `composer remove`. Mit dem Paket
 * verschwinden auch seine Migrationsdateien – danach kann niemand mehr
 * zurückrollen, die Tabellen blieben für immer verwaist stehen.
 *
 * Standardmäßig werden nur die Registrierungsdaten entfernt (Modul, Menüpunkte,
 * Rollen-Zuordnungen, sprechende Adressen). Die Tabellen des Moduls bleiben
 * stehen, solange nicht ausdrücklich `--mit-daten` gesetzt ist.
 */
class UninstallModule extends Command
{
    protected $signature = 'modules:uninstall
        {key : Modul-Schlüssel, z. B. userimport}
        {--mit-daten : Auch die Migrationen des Moduls zurückrollen (löscht dessen Tabellen samt Inhalt)}
        {--dry-run : Nur anzeigen, was passieren würde}';

    protected $description = 'Ein Modul aus dieser Instanz entfernen (Gegenstück zu modules:sync).';

    public function handle(ModuleRegistry $registry): int
    {
        $key = $this->argument('key');
        $module = Module::with('menuItems')->where('key', $key)->first();
        $manifest = $registry->manifest($key);

        if (! $module && ! $manifest) {
            $this->error("Kein Modul mit dem Schlüssel „{$key}\" gefunden.");
            $this->line('Bekannte Schlüssel: '.(Module::orderBy('key')->pluck('key')->implode(', ') ?: '–'));

            return self::FAILURE;
        }

        $trocken = (bool) $this->option('dry-run');
        $mitDaten = (bool) $this->option('mit-daten');

        $this->zusammenfassung($key, $module, $manifest, $mitDaten);

        if ($mitDaten && ! $manifest) {
            $this->newLine();
            $this->error('Das Paket ist nicht mehr installiert – seine Migrationen sind weg und');
            $this->error('können nicht zurückgerollt werden. Ohne --mit-daten räumt der Befehl');
            $this->error('immerhin die Registrierung auf; für die Tabellen das Paket kurz erneut');
            $this->error('einbinden (composer require …) und dann hier fortfahren.');

            return self::FAILURE;
        }

        if ($trocken) {
            $this->newLine();
            $this->comment('Probelauf – es wurde nichts verändert.');

            return self::SUCCESS;
        }

        $frage = $mitDaten
            ? "Modul „{$key}\" entfernen UND seine Tabellen samt Inhalt löschen?"
            : "Modul „{$key}\" aus der Registrierung entfernen?";

        if (! $this->confirm($frage, false)) {
            $this->comment('Abgebrochen – es wurde nichts verändert.');

            return self::SUCCESS;
        }

        // Erst die Migrationen: Schlägt das fehl, steht die Registrierung noch
        // und der Befehl lässt sich unverändert wiederholen.
        if ($mitDaten) {
            $this->migrationenZurueckrollen($manifest);
        }

        $this->registrierungEntfernen($key, $module);

        $this->newLine();
        $this->info("Modul „{$key}\" ist aus dieser Instanz entfernt.");

        if ($manifest) {
            $this->line('Jetzt noch das Paket entfernen, z. B.:');
            $this->line('  <comment>composer remove '.($this->paketName($manifest) ?? "<vendor>/module-{$key}").'</comment>');
        }

        return self::SUCCESS;
    }

    /** Zeigt vor jeder Änderung, was auf dem Spiel steht. */
    private function zusammenfassung(string $key, ?Module $module, ?ModuleManifest $manifest, bool $mitDaten): void
    {
        $this->newLine();
        $this->line("<options=bold>Modul „{$key}\"</>");
        $this->line('  Paket installiert: '.($manifest ? '<info>ja</info>' : '<comment>nein (nur noch Registrierungs-Reste)</comment>'));

        if ($module) {
            $this->line("  Menüpunkte: {$module->menuItems->count()}");
            foreach ($module->menuItems as $item) {
                $rollen = $item->roles()->pluck('roles.role_id')->implode(', ');
                $this->line("    – {$item->label} ({$item->route_name})".($rollen ? " [Rollen: {$rollen}]" : ''));
            }
            $this->line('  Sprechende Adressen: '.$this->routenEinstellungen($key)->count());
        } else {
            $this->line('  <comment>Keine Registrierung in der Datenbank (nur das Paket ist da).</comment>');
        }

        if (! $manifest) {
            return;
        }

        $offen = $this->gelaufeneMigrationen($manifest);
        $this->line('  Migrationen (gelaufen): '.count($offen));

        foreach ($offen as $datei) {
            $tabellen = $this->tabellenAus($datei);
            $zusatz = [];
            foreach ($tabellen as $tabelle) {
                $zusatz[] = Schema::hasTable($tabelle)
                    ? "{$tabelle}: ".DB::table($tabelle)->count().' Zeilen'
                    : "{$tabelle}: nicht vorhanden";
            }
            $this->line('    – '.basename($datei, '.php').($zusatz ? ' → '.implode(', ', $zusatz) : ''));
        }

        $this->newLine();
        $this->line($mitDaten
            ? '  <fg=red;options=bold>--mit-daten: Diese Migrationen werden zurückgerollt – die Tabellen samt Inhalt sind danach weg.</>'
            : '  <info>Die Tabellen des Moduls bleiben unangetastet (--mit-daten würde sie zurückrollen).</info>');
    }

    /** Migrationsdateien des Moduls, die laut `migrations`-Tabelle gelaufen sind. */
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
     * gelesen – rein für die Anzeige vor der Sicherheitsabfrage. Findet die
     * Suche nichts, wird eben nur der Dateiname genannt.
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
     * Rollt die Migrationen des Moduls zurück – neueste zuerst, so wie
     * Laravel es täte, aber gezielt nur die dieses Moduls.
     *
     * `migrate:rollback --path=…` kommt dafür nicht in Frage: Es rollt immer
     * den letzten Stapel zurück, und der enthält typischerweise Migrationen
     * ganz anderer Pakete.
     */
    private function migrationenZurueckrollen(ModuleManifest $manifest): void
    {
        $dateien = array_reverse($this->gelaufeneMigrationen($manifest));

        if (! $dateien) {
            $this->line('  Keine gelaufenen Migrationen – nichts zurückzurollen.');

            return;
        }

        foreach ($dateien as $datei) {
            $name = basename($datei, '.php');
            // Modul-Migrationen sind anonyme Klassen: Die Datei liefert das
            // fertige Objekt zurück (nichts anderes macht Laravels Migrator).
            $migration = require $datei;

            if (is_object($migration) && method_exists($migration, 'down')) {
                $migration->down();
            } else {
                $this->warn("  ! {$name} hat kein down() – übersprungen, Tabelle bleibt stehen.");

                continue;
            }

            DB::table('migrations')->where('migration', $name)->delete();

            $this->line("  <info>↩</info> zurückgerollt: {$name}");
        }
    }

    /**
     * Entfernt Modul, Menüpunkte (per Fremdschlüssel-Kaskade samt
     * Rollen-Zuordnungen) und die sprechenden Adressen des Moduls.
     */
    private function registrierungEntfernen(string $key, ?Module $module): void
    {
        $adressen = $this->routenEinstellungen($key)->delete();

        if ($adressen) {
            $this->line("  <info>✓</info> {$adressen} sprechende Adresse(n) entfernt");
        }

        if (! $module) {
            return;
        }

        $punkte = $module->menuItems->count();
        $module->delete();

        $this->line("  <info>✓</info> Modul-Eintrag und {$punkte} Menüpunkt(e) entfernt");
    }

    /** Alle Routen-Einstellungen, deren Route zu diesem Modul gehört. */
    private function routenEinstellungen(string $key)
    {
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
