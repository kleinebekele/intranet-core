<?php

namespace App\Ekkon\Support;

use App\Ekkon\Tasks\EkkonTask;
use App\Models\Module;
use App\Modules\Support\ModuleRegistry;
use LogicException;
use ReflectionClass;

/**
 * Findet alle Task-Klassen der angemeldeten Pakete.
 *
 * Jedes Paket meldet sein Tasks-Verzeichnis per addSource() an – das Basis-Paket
 * genauso wie jedes Submodul. Aufgelöst wird erst beim ersten all(); dadurch ist
 * die Reihenfolge, in der Laravel die Provider lädt, egal.
 *
 * Task-Klassen liegen unter <Quelle>/<Gruppe>/<Name>.php; der Key ergibt sich als
 * "Gruppe/Name". Registrieren muss man einen Task nicht – Klasse anlegen genügt.
 */
class TaskRegistry
{
    /** @var list<array{dir: string, ns: string, paket: string}> */
    private array $sources = [];

    /** @var array<string, EkkonTask>|null key => Task-Instanz */
    private ?array $tasks = null;

    /** @var list<array{key: string, behalten: string, verworfen: string, paket: string}> */
    private array $kollisionen = [];

    /** Modul-Angabe der Core-eigenen Tasks: gehören keinem Modul, laufen immer. */
    public const SYSTEM = '@system';

    /** @var array<string, string|null> Task-Key => Modul-Key (SYSTEM, Key oder null = unbekannt) */
    private array $modulJeTask = [];

    /** @var array<string, bool>|null Modul-Key => in der Modulverwaltung aktiv */
    private ?array $modulStatus = null;

    /**
     * Tasks-Verzeichnis eines Pakets anmelden. Gehört in register() des Providers,
     * NICHT in boot() – sonst kann die Registry schon aufgelöst sein.
     *
     * @param  string  $dir  absoluter Pfad auf das Tasks-Verzeichnis
     * @param  string  $ns  Namespace darunter, z. B. Intranet\Modules\EkkonJtl\Tasks
     * @param  string  $paket  Paketname im Klartext – erscheint in Kollisions-Meldungen
     * @param  string|null  $modul  Modul-Key aus dem Manifest (`ModuleManifest::make('…')`).
     *                              Daran hängt, ob die Tasks laufen (Modul aktiv?) und wo
     *                              sie in der Übersicht stehen. Ohne Angabe wird der Key
     *                              aus dem Paketnamen erraten (`vendor/module-<key>`).
     */
    public function addSource(string $dir, string $ns, string $paket, ?string $modul = null): void
    {
        if ($this->tasks !== null) {
            throw new LogicException(
                "TaskRegistry ist bereits aufgelöst – {$paket} meldet sich zu spät an. "
                .'addSource() gehört in register() des Providers, nicht in boot().'
            );
        }

        $this->sources[] = [
            'dir' => rtrim($dir, '/\\'),
            'ns' => trim($ns, '\\'),
            'paket' => $paket,
            'modul' => $modul,
        ];
    }

    /** @return array<string, EkkonTask> */
    public function all(): array
    {
        if ($this->tasks !== null) {
            return $this->tasks;
        }

        $tasks = [];

        foreach ($this->sources as $source) {
            foreach (glob($source['dir'].'/*/*.php') ?: [] as $file) {
                $class = $source['ns'].'\\'.basename(dirname($file)).'\\'.basename($file, '.php');

                if (! class_exists($class) || ! is_subclass_of($class, EkkonTask::class)) {
                    continue;
                }
                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                /** @var EkkonTask $task */
                $task = new $class();
                $key = $task->key();

                // Zwei Tasks unter einem Key wären fatal: Der Key ist zugleich
                // Historien-Schlüssel, Cache-Lock, Route-Parameter und Pause-Schalter –
                // die beiden würden sich Lauf-Historie und Pause teilen. Erster gewinnt,
                // der zweite wird verworfen und GEMELDET (Dashboard + ekkon:task).
                // Bewusst keine Exception: ein Fehler im register() eines Nebenmoduls
                // würde sonst das ganze Intranet in einen 500er reißen, Login inklusive.
                if (isset($tasks[$key])) {
                    $this->kollisionen[] = [
                        'key' => $key,
                        'behalten' => $tasks[$key]::class,
                        'verworfen' => $class,
                        'paket' => $source['paket'],
                    ];

                    continue;
                }

                $tasks[$key] = $task;
                $this->modulJeTask[$key] = $this->modulDerQuelle($source);
            }
        }

        ksort($tasks);

        return $this->tasks = $tasks;
    }

    /**
     * Modul-Key einer Quelle: die ausdrückliche Angabe – oder, bei Modulen aus
     * der Zeit davor, aus dem Paketnamen erraten (`do1emu/module-bi` → `bi`),
     * sofern ein Modul mit diesem Key installiert ist.
     *
     * @param  array{paket: string, modul: string|null}  $source
     */
    private function modulDerQuelle(array $source): ?string
    {
        if ($source['modul'] !== null) {
            return $source['modul'];
        }

        $name = preg_replace('/^module-/', '', basename($source['paket']));

        return in_array($name, app(ModuleRegistry::class)->keys(), true) ? $name : null;
    }

    /** Modul-Key des Tasks: ein Modul, SYSTEM (Core-eigen) oder null (unbekannt). */
    public function modulFuer(string $key): ?string
    {
        $this->all();

        return $this->modulJeTask[$key] ?? null;
    }

    /**
     * Darf der Task laufen, was sein Modul angeht? Nur ein in der Modulverwaltung
     * ausdrücklich DEAKTIVIERTES Modul hält seine Tasks an. Core-Tasks, Tasks ohne
     * erkennbares Modul und Module, die noch nie synchronisiert wurden, laufen –
     * eine frische Installation soll nicht stumm bleiben.
     */
    public function modulAktiv(string $key): bool
    {
        $modul = $this->modulFuer($key);

        if ($modul === null || $modul === self::SYSTEM) {
            return true;
        }

        return $this->modulStatus()[$modul] ?? true;
    }

    /** @return array<string, bool> */
    private function modulStatus(): array
    {
        if ($this->modulStatus !== null) {
            return $this->modulStatus;
        }

        try {
            return $this->modulStatus = Module::query()->pluck('is_enabled', 'key')
                ->map(fn ($an) => (bool) $an)->all();
        } catch (\Throwable) {
            // Noch keine modules-Tabelle (allererste Migration) – nichts anhalten.
            return $this->modulStatus = [];
        }
    }

    /** Nach dem Umschalten eines Moduls im selben Prozess neu lesen. */
    public function modulStatusVergessen(): void
    {
        $this->modulStatus = null;
    }

    /**
     * Alle Tasks nach Modul gruppiert, darunter nach Kategorie – die feste
     * Ordnung überall, wo Tasks aufgelistet werden. Reihenfolge: System zuerst,
     * dann aktive Module nach Name, deaktivierte ans Ende, „Ohne Modul" zuletzt.
     *
     * @return list<array{key: string|null, name: string, icon: string|null, aktiv: bool, system: bool, kategorien: array<string, array<string, EkkonTask>>}>
     */
    public function byModule(): array
    {
        $manifeste = app(ModuleRegistry::class);
        $gruppen = [];

        foreach ($this->all() as $key => $task) {
            $modul = $this->modulFuer($key);
            $id = $modul ?? '';

            $gruppen[$id] ??= [
                'key' => $modul,
                'name' => match (true) {
                    $modul === self::SYSTEM => 'System',
                    $modul === null => 'Ohne Modul',
                    default => $manifeste->manifest($modul)?->name ?? $modul,
                },
                'icon' => $modul && $modul !== self::SYSTEM ? $manifeste->manifest($modul)?->icon : null,
                'aktiv' => $this->modulAktiv($key),
                'system' => $modul === self::SYSTEM,
                'kategorien' => [],
            ];

            $gruppen[$id]['kategorien'][$task->category][$key] = $task;
        }

        foreach ($gruppen as &$gruppe) {
            ksort($gruppe['kategorien']);
        }
        unset($gruppe);

        $rang = fn (array $g): string => match (true) {
            $g['system'] => '0',
            $g['key'] === null => '3',
            ! $g['aktiv'] => '2',
            default => '1',
        }.mb_strtolower($g['name']);

        usort($gruppen, fn (array $a, array $b) => strcmp($rang($a), $rang($b)));

        return $gruppen;
    }

    /** @return array<string, array<string, EkkonTask>> Kategorie => (key => Task) */
    public function byCategory(): array
    {
        $grouped = [];
        foreach ($this->all() as $key => $task) {
            $grouped[$task->category][$key] = $task;
        }
        ksort($grouped);

        return $grouped;
    }

    public function find(string $key): ?EkkonTask
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Verworfene Tasks wegen doppelt vergebenem Key. Leer = alles in Ordnung.
     *
     * @return list<array{key: string, behalten: string, verworfen: string, paket: string}>
     */
    public function kollisionen(): array
    {
        $this->all();

        return $this->kollisionen;
    }

    /**
     * Zu welchem Modul gehört eine Meldungsart? schluessel => Modulname – damit
     * auch Auswahllisten nach Modul gruppieren können (siehe byModule()).
     *
     * @return array<string, string>
     */
    public function meldungsartModule(): array
    {
        $zuordnung = [self::MELDUNG_LAUFZEIT => 'System'];

        foreach ($this->byModule() as $gruppe) {
            foreach ($gruppe['kategorien'] as $tasks) {
                foreach ($tasks as $task) {
                    foreach (array_keys($task->meldungsarten) as $art) {
                        $zuordnung[$art] ??= $gruppe['name'];
                    }
                }
            }
        }

        return $zuordnung;
    }

    /**
     * Meldungsart des Runners: Ein Lauf hat ungewoehnlich lange gedauert.
     *
     * Nach dem Vorfall am 2026-08-05: Der Task lief stundenlang, und gemerkt
     * hat es niemand am System, sondern daran, dass die Wawi lahm wurde. Eine
     * Ueberwachung, die nur eine Tabellenzelle einfaerbt, sieht man erst, wenn
     * man ohnehin schon nachschaut.
     */
    public const MELDUNG_LAUFZEIT = 'task-laufzeit';

    /**
     * Alle Meldungsarten, die irgendein Task deklariert (EkkonTask::$meldungsarten):
     * schluessel => "Klartext (Task/Key)".
     *
     * Quelle für das Dropdown in der Benachrichtigungs-Maske. Bewusst KEIN
     * Freitext: Ein Tippfehler in der Meldungsart würde die Meldung lautlos ins
     * Nichts routen – man legt eine Route an, sie sieht richtig aus, und
     * niemand wird informiert.
     *
     * @return array<string, string>
     */
    public function meldungsarten(): array
    {
        // Meldungsarten des Systems selbst - sie gehoeren keinem Task, sondern
        // gelten fuer alle. Muessen hier stehen, sonst liesse sich dafuer keine
        // Route anlegen und die Meldung landete immer bei 'ohne_ziel'.
        $arten = [
            self::MELDUNG_LAUFZEIT => 'Ein Task lief ungewöhnlich lange (System)',
        ];

        foreach ($this->all() as $key => $task) {
            foreach ($task->meldungsarten as $art => $klartext) {
                $arten[$art] = $klartext.' ('.$key.')';
            }
        }

        ksort($arten);

        return $arten;
    }
}
