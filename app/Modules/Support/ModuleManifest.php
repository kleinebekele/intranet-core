<?php

namespace App\Modules\Support;

/**
 * The "business card" of a module: it tells the core everything it needs to
 * show the module in the navigation and admin panel.
 *
 * A module builds its manifest fluently inside its service provider:
 *
 *   return ModuleManifest::make('news', 'Neuigkeiten', icon: 'newspaper')
 *       ->item('index', 'Übersicht', 'module.news')
 *       ->item('create', 'Beitrag anlegen', 'module.news.create')
 *       ->rolle('news-redaktion', 'News-Redaktion');
 */
class ModuleManifest
{
    /**
     * Wurzelverzeichnis des Pakets. Setzt der ModuleServiceProvider selbst –
     * ein Modul muss sich darum nicht kümmern. Daran erkennt der Core, welche
     * Migrationen zu diesem Modul gehören (siehe `modules:uninstall`).
     */
    public ?string $basePath = null;

    /**
     * Fester Bestandteil des Cores (z. B. Ekkon): tritt als Modul auf, lässt
     * sich aber nicht deinstallieren – seine Tabellen gehören dem Core.
     */
    public bool $fest = false;

    /**
     * Hauptpunkt (siehe {@see hauptpunkt()}): steht in der Seitenleiste direkt
     * unter „Startseite" statt in der Modulliste.
     */
    public bool $hauptpunkt = false;

    /**
     * Rollen, die dieses Modul mitbringt (siehe {@see rolle()}).
     *
     * @var ModuleRole[]
     */
    public array $rollen = [];

    /**
     * Abweichende Zugriffsstufen je Route (siehe {@see stufe()}).
     *
     * @var array<string, Zugriffsstufe> voller Routenname => Stufe
     */
    public array $stufen = [];

    /**
     * Routenbereiche, die zu einem Menüpunkt gehören, obwohl ihr Name nicht
     * darunter liegt (siehe {@see gehoertZu()}).
     *
     * @var array<string, string> voller Routen-Präfix => Menüpunkt-Key
     */
    public array $zuordnungen = [];

    /** @param  MenuItem[]  $items */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $icon = null,
        public int $position = 0,
        public array $items = [],
    ) {}

    /**
     * Die Migrationsdateien dieses Moduls, aufsteigend sortiert.
     *
     * @return string[]
     */
    public function migrationFiles(): array
    {
        if (! $this->basePath || ! is_dir($dir = "{$this->basePath}/database/migrations")) {
            return [];
        }

        $files = glob("{$dir}/*.php") ?: [];
        sort($files);

        return $files;
    }

    public static function make(string $key, string $name, ?string $icon = null, int $position = 0): static
    {
        return new static($key, $name, $icon, $position);
    }

    /**
     * Register a sub-page (menu item) of this module.
     *
     * @param  string  $key  Stable identifier, unique within the module.
     * @param  string  $label  Text shown in the left menu.
     * @param  string  $routeName  Laravel route name this item links to.
     * @param  string|null  $icon  Icon-Name (siehe x-module-icon); null = neutraler Punkt.
     * @param  int  $position  Default order (admin can override later).
     * @param  bool  $adminsOnly  Nur für Admins sichtbar (beim Anlegen via modules:sync gesetzt).
     * @param  string|null  $group  Überschrift, unter der dieser Punkt im Menü
     *                              aufklappbar gebündelt wird; null = eigene Zeile.
     *                              Rein optisch – der Punkt bleibt ein eigener
     *                              Eintrag mit eigenen Rollen und eigener
     *                              Zugriffsregel.
     * @param  \Closure|null  $visibleWhen  Optionaler Laufzeit-Filter (siehe MenuItem):
     *                              false = Punkt ausblenden. Für Zustände jenseits
     *                              der Rollen (z. B. ein Saison-Schalter).
     */
    public function item(string $key, string $label, string $routeName, ?string $icon = null, int $position = 0, bool $adminsOnly = false, ?string $group = null, ?\Closure $visibleWhen = null): static
    {
        $this->items[] = new MenuItem(
            key: $key,
            label: $label,
            routeName: $routeName,
            position: $position ?: count($this->items),
            icon: $icon,
            adminsOnly: $adminsOnly,
            group: $group,
            visibleWhen: $visibleWhen,
        );

        return $this;
    }

    /**
     * Das Modul als Hauptpunkt führen: Es steht in der Seitenleiste direkt unter
     * „Startseite" (oberhalb von „Module"), und beim Öffnen bleibt die Leiste der
     * Startseite stehen, statt in die Modulansicht zu wechseln. Gedacht für Module
     * mit einer einzigen Seite, die sich wie ein Teil des Intranets anfühlen sollen
     * (z. B. Webmail). Sichtbarkeit und Rollen wie bei jedem Modul.
     */
    public function hauptpunkt(): static
    {
        $this->hauptpunkt = true;

        return $this;
    }

    /**
     * Eine Rolle anmelden, die dieses Modul mitbringt. `modules:sync` legt sie
     * an und ordnet sie dem Modul zu; sie gilt nur, solange das Modul aktiv ist.
     *
     * @param  string  $roleId  Stabiler Schlüssel (`roles.role_id`), z. B. 'kantine_koch'.
     * @param  string  $name  Anzeigename im Rollen-Panel.
     * @param  bool  $plattformweit  Auch bei anderen Modulen wählbar (z. B. „Lehrer").
     */
    public function rolle(string $roleId, string $name, bool $plattformweit = false): static
    {
        $this->rollen[] = new ModuleRole($roleId, $name, $plattformweit);

        return $this;
    }

    /**
     * Welche Zugriffsstufe eine Route braucht, wenn die Regel aus der
     * Anfrageart nicht passt (siehe {@see Zugriffsstufe::benoetigt()}) – etwa
     * ein POST, der nur sucht, oder ein POST, der etwas anlegt, aber nicht
     * `*.store` heißt.
     *
     * Routennamen dürfen ohne `module.{key}.` angegeben werden:
     *   ->stufe(Zugriffsstufe::Lesen, 'servings.lookup', 'servings.terminal.search')
     */
    public function stufe(Zugriffsstufe|string $stufe, string ...$routeNames): static
    {
        $praefix = "module.{$this->key}.";

        foreach ($routeNames as $routeName) {
            $voll = str_starts_with($routeName, $praefix) ? $routeName : $praefix.$routeName;
            $this->stufen[$voll] = Zugriffsstufe::aus($stufe);
        }

        return $this;
    }

    /**
     * Routenbereiche einem Menüpunkt zuordnen, wenn ihr Name nicht unter dessen
     * Route liegt – etwa `menu-templates.*`, die auf der Saison-Seite gepflegt
     * werden. Ohne Zuordnung bekäme so ein Bereich die höchste Stufe, die der
     * Benutzer irgendwo im Modul hat.
     *
     *   ->gehoertZu('seasons', 'menu-templates')   // module.{key}.menu-templates.* → Menüpunkt „seasons"
     */
    public function gehoertZu(string $menuepunktKey, string ...$routenBereiche): static
    {
        $praefix = "module.{$this->key}.";

        foreach ($routenBereiche as $bereich) {
            $voll = str_starts_with($bereich, $praefix) ? $bereich : $praefix.$bereich;
            $this->zuordnungen[rtrim($voll, '.')] = $menuepunktKey;
        }

        return $this;
    }

    /** Kurzform für {@see stufe()} mit `lesen`: POST-Routen, die nichts ändern. */
    public function lesend(string ...$routeNames): static
    {
        return $this->stufe(Zugriffsstufe::Lesen, ...$routeNames);
    }
}
