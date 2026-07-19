<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Modules\Support\ModuleUninstaller;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Das Gegenstück zu `modules:sync`: entfernt ein Modul sauber aus der Instanz.
 *
 *   php artisan modules:uninstall <key>              nur die Registrierung
 *   php artisan modules:uninstall <key> --mit-daten  zusätzlich Tabellen zurückrollen
 *   php artisan modules:uninstall <key> --dry-run    nur anzeigen, nichts tun
 *
 * Dasselbe gibt es als Knopf unter Verwaltung → Module; beide Wege nutzen den
 * {@see ModuleUninstaller}.
 *
 * ⚠️ REIHENFOLGE: erst dieser Befehl, DANN `composer remove`. Mit dem Paket
 * verschwinden auch seine Migrationsdateien – danach kann niemand mehr
 * zurückrollen, die Tabellen blieben für immer verwaist stehen.
 */
class UninstallModule extends Command
{
    protected $signature = 'modules:uninstall
        {key : Modul-Schlüssel, z. B. userimport}
        {--mit-daten : Auch die Migrationen des Moduls zurückrollen (löscht dessen Tabellen samt Inhalt)}
        {--dry-run : Nur anzeigen, was passieren würde}';

    protected $description = 'Ein Modul aus dieser Instanz entfernen (Gegenstück zu modules:sync).';

    public function handle(ModuleUninstaller $uninstaller): int
    {
        $key = $this->argument('key');
        $vorschau = $uninstaller->vorschau($key);

        if (! $vorschau) {
            $this->error("Kein Modul mit dem Schlüssel „{$key}\" gefunden.");
            $this->line('Bekannte Schlüssel: '.(Module::orderBy('key')->pluck('key')->implode(', ') ?: '–'));

            return self::FAILURE;
        }

        $mitDaten = (bool) $this->option('mit-daten');

        $this->zusammenfassung($vorschau, $mitDaten);

        if ($mitDaten && ! $vorschau['paket_installiert']) {
            $this->newLine();
            $this->error('Das Paket ist nicht mehr installiert – seine Migrationen sind weg und');
            $this->error('können nicht zurückgerollt werden. Ohne --mit-daten räumt der Befehl');
            $this->error('immerhin die Registrierung auf; für die Tabellen das Paket kurz erneut');
            $this->error('einbinden (composer require …) und dann hier fortfahren.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
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

        try {
            $bericht = $uninstaller->entfernen($key, $mitDaten);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($bericht['migrationen'] as $name) {
            $this->line("  <info>↩</info> zurückgerollt: {$name}");
        }
        if ($bericht['adressen']) {
            $this->line("  <info>✓</info> {$bericht['adressen']} sprechende Adresse(n) entfernt");
        }
        $this->line("  <info>✓</info> Modul-Eintrag und {$bericht['menuepunkte']} Menüpunkt(e) entfernt");

        $this->newLine();
        $this->info("Modul „{$key}\" ist aus dieser Instanz entfernt.");

        if ($vorschau['paket_installiert']) {
            $this->line('Jetzt noch das Paket entfernen, z. B.:');
            $this->line('  <comment>composer remove '.($bericht['paket_name'] ?? "<vendor>/module-{$key}").'</comment>');
        }

        return self::SUCCESS;
    }

    /** Zeigt vor jeder Änderung, was auf dem Spiel steht. */
    private function zusammenfassung(array $vorschau, bool $mitDaten): void
    {
        $this->newLine();
        $this->line("<options=bold>Modul „{$vorschau['key']}\"</>");
        $this->line('  Paket installiert: '.($vorschau['paket_installiert']
            ? '<info>ja</info>'
            : '<comment>nein (nur noch Registrierungs-Reste)</comment>'));

        if ($vorschau['modul']) {
            $this->line('  Menüpunkte: '.$vorschau['menuepunkte']->count());
            foreach ($vorschau['menuepunkte'] as $item) {
                $rollen = $item->roles->pluck('role_id')->implode(', ');
                $this->line("    – {$item->label} ({$item->route_name})".($rollen ? " [Rollen: {$rollen}]" : ''));
            }
            $this->line("  Sprechende Adressen: {$vorschau['adressen']}");
        } else {
            $this->line('  <comment>Keine Registrierung in der Datenbank (nur das Paket ist da).</comment>');
        }

        if (! $vorschau['paket_installiert']) {
            return;
        }

        $this->line('  Migrationen (gelaufen): '.count($vorschau['migrationen']));

        foreach ($vorschau['migrationen'] as $migration) {
            $zusatz = array_map(
                fn (array $t): string => $t['vorhanden']
                    ? "{$t['name']}: {$t['zeilen']} Zeilen"
                    : "{$t['name']}: nicht vorhanden",
                $migration['tabellen'],
            );
            $this->line("    – {$migration['name']}".($zusatz ? ' → '.implode(', ', $zusatz) : ''));
        }

        $this->newLine();
        $this->line($mitDaten
            ? '  <fg=red;options=bold>--mit-daten: Diese Migrationen werden zurückgerollt – die Tabellen samt Inhalt sind danach weg.</>'
            : '  <info>Die Tabellen des Moduls bleiben unangetastet (--mit-daten würde sie zurückrollen).</info>');
    }
}
